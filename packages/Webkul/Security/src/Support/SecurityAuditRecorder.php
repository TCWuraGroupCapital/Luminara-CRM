<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Webkul\Workspace\WorkspaceManager;

final class SecurityAuditRecorder
{
    public const BATCH_SIZE = 100;

    private const REDACT_PATTERN = '/(password|secret|token|api_key|private_key|authorization|cookie|recovery_code)/i';

    private const ACTIONS = ['created', 'updated', 'deleted', 'exported'];

    /** @var array<string, true> */
    private static array $verifiedDatabases = [];

    /** @var list<array<string, mixed>> */
    private static array $pending = [];

    private static ?string $pendingDatabase = null;

    private static bool $defer = false;

    private static bool $flushing = false;

    /**
     * Append a sanitized, immutable security audit event without disrupting caller flow if schema is pre-install.
     *
     * Rows are written immediately during tests. Otherwise they are held and inserted in batches so a mass
     * update does not issue one insert per record. The batch is flushed before the workspace connection changes.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        string $event,
        ?string $auditableType = null,
        string|int|null $auditableId = null,
        ?array $metadata = null,
        ?int $actorId = null,
        ?string $actorEmail = null
    ): void {
        try {
            $row = self::makeRow($event, $auditableType, $auditableId, $metadata, $actorId, $actorEmail);

            if ($row === null) {
                return;
            }

            self::enqueue($row);
        } catch (Throwable $e) {
            if (self::tableWasKnown()) {
                report($e);
            }
        }
    }

    /**
     * Add field names to an open create or update for the same entity, or record a new update.
     *
     * Used for custom attributes, whose values live in another table. Only the attribute code is accepted.
     *
     * @param  list<string>  $fields
     */
    public static function noteFields(string $type, string|int $id, array $fields): void
    {
        try {
            $fields = self::fieldNames($fields);

            if ($fields === []) {
                return;
            }

            $idString = (string) $id;

            foreach (self::$pending as $index => $row) {
                $event = $row['event'] ?? null;

                if (
                    ($row['auditable_type'] ?? null) === $type
                    && (string) ($row['auditable_id'] ?? '') === $idString
                    && ($event === $type.'.created' || $event === $type.'.updated')
                ) {
                    self::$pending[$index]['metadata'] = self::mergeFieldMetadata($row['metadata'] ?? null, $fields);

                    return;
                }
            }

            self::record($type.'.updated', $type, $id, [
                'action' => 'updated',
                'fields' => $fields,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Hold rows until flush, including under tests. Pass false to write the buffer and return to immediate writes.
     */
    public static function deferWrites(bool $defer): void
    {
        if (! $defer) {
            self::flush();
        }

        self::$defer = $defer;
    }

    public static function flush(): void
    {
        if (self::$pending === [] || self::$flushing) {
            return;
        }

        if (self::$pendingDatabase !== null && self::$pendingDatabase !== self::currentDatabase()) {
            self::$pending = [];
            self::$pendingDatabase = null;
            report(new RuntimeException('Security audit buffer was discarded because the database changed before it was written.'));

            return;
        }

        if (! self::tableReady()) {
            self::$pending = [];
            self::$pendingDatabase = null;

            return;
        }

        self::$flushing = true;
        $rows = self::$pending;
        self::$pending = [];
        self::$pendingDatabase = null;

        try {
            foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
                DB::table('security_audit_logs')->insert($chunk);
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            self::$flushing = false;
        }
    }

    public static function resetTableCache(): void
    {
        self::$verifiedDatabases = [];
    }

    /**
     * Forget buffered rows and the request id. Called when the application boots so tests cannot leak state.
     */
    public static function resetState(): void
    {
        self::$pending = [];
        self::$pendingDatabase = null;
        self::$defer = false;
        self::$flushing = false;
        self::$verifiedDatabases = [];
        AuditContext::set(null);
    }

    /**
     * Keep only identifier-shaped field names. Values, and any list that is not a plain list of names, are dropped.
     *
     * @param  array<mixed>  $names
     * @return list<string>
     */
    public static function fieldNames(array $names): array
    {
        if (! array_is_list($names)) {
            return [];
        }

        $clean = [];

        foreach ($names as $name) {
            if (! is_string($name) || preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name) !== 1) {
                continue;
            }

            $clean[$name] = $name;
        }

        $list = array_values($clean);
        sort($list);

        return array_slice($list, 0, 100);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function enqueue(array $row): void
    {
        $database = self::currentDatabase();

        if (self::$flushing) {
            DB::table('security_audit_logs')->insert([$row]);

            return;
        }

        if (self::shouldBuffer()) {
            if (self::$pending !== [] && self::$pendingDatabase !== null && self::$pendingDatabase !== $database) {
                self::flush();
            }

            self::$pending[] = $row;
            self::$pendingDatabase = $database;

            if (count(self::$pending) >= self::BATCH_SIZE) {
                self::flush();
            }

            return;
        }

        if (! self::tableReady()) {
            return;
        }

        self::$flushing = true;

        try {
            DB::table('security_audit_logs')->insert([$row]);
        } finally {
            self::$flushing = false;
        }
    }

    private static function shouldBuffer(): bool
    {
        return self::$defer || ! app()->runningUnitTests();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private static function makeRow(
        string $event,
        ?string $auditableType,
        string|int|null $auditableId,
        ?array $metadata,
        ?int $actorId,
        ?string $actorEmail
    ): ?array {
        if (! self::tableReady()) {
            return null;
        }

        $user = auth()->guard('user')->user();
        $resolvedActorId = $actorId ?? ($user ? (int) $user->getAuthIdentifier() : null);
        $resolvedActorEmail = $actorEmail ?? ($user->email ?? null);
        $req = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $cleanMetadata = $metadata !== null ? self::prepareMetadata($metadata) : null;

        return [
            'actor_id' => $resolvedActorId,
            'actor_email' => self::cleanUtf8($resolvedActorEmail !== null ? (string) $resolvedActorEmail : null, 191),
            'event' => self::cleanUtf8($event, 96) ?? 'unknown',
            'auditable_type' => self::cleanUtf8($auditableType, 96),
            'auditable_id' => self::cleanUtf8($auditableId !== null ? (string) $auditableId : null, 64),
            'ip_address' => self::cleanUtf8($req?->ip() ? (string) $req->ip() : null, 45),
            'user_agent' => self::cleanUtf8($req?->userAgent() ? (string) $req->userAgent() : null, 255),
            'request_id' => self::cleanUtf8(AuditContext::id(), 64),
            'workspace' => self::cleanUtf8(self::workspaceSlug(), 191),
            'metadata' => $cleanMetadata === null ? null : json_encode($cleanMetadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private static function workspaceSlug(): ?string
    {
        if (! class_exists(WorkspaceManager::class) || ! app()->bound(WorkspaceManager::class)) {
            return null;
        }

        $slug = app(WorkspaceManager::class)->current()?->slug;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    private static function tableReady(): bool
    {
        $database = self::currentDatabase();

        if (isset(self::$verifiedDatabases[$database])) {
            return true;
        }

        if (! Schema::hasTable('security_audit_logs') || ! Schema::hasColumn('security_audit_logs', 'request_id')) {
            return false;
        }

        self::$verifiedDatabases[$database] = true;

        return true;
    }

    private static function tableWasKnown(): bool
    {
        return isset(self::$verifiedDatabases[self::currentDatabase()]);
    }

    private static function currentDatabase(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.database");
    }

    /**
     * @param  list<string>  $fields
     */
    private static function mergeFieldMetadata(mixed $metadata, array $fields): string
    {
        $decoded = is_string($metadata) ? json_decode($metadata, true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        $existing = isset($decoded['fields']) && is_array($decoded['fields']) ? $decoded['fields'] : [];
        $decoded['fields'] = self::fieldNames(array_values(array_filter(
            [...$existing, ...$fields],
            'is_string'
        )));
        $decoded['action'] = $decoded['action'] ?? 'updated';

        return json_encode(self::prepareMetadata($decoded), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private static function cleanUtf8(?string $value, int $maxChars): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $scrubbed = mb_scrub($value, 'UTF-8');

        return mb_substr($scrubbed, 0, $maxChars, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function prepareMetadata(array $metadata): array
    {
        $clean = self::sanitizeMetadata($metadata);

        if (array_key_exists('fields', $clean)) {
            $clean['fields'] = self::fieldNames(is_array($clean['fields']) ? $clean['fields'] : []);
        }

        if (array_key_exists('action', $clean) && ! in_array($clean['action'], self::ACTIONS, true)) {
            unset($clean['action']);
        }

        if (
            array_key_exists('format', $clean)
            && (! is_string($clean['format']) || preg_match('/^[a-z0-9_]{1,32}$/', $clean['format']) !== 1)
        ) {
            unset($clean['format']);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitizeMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = (string) $key;

            if (preg_match(self::REDACT_PATTERN, $normalizedKey) === 1) {
                $clean[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitizeMetadata($value);
            } elseif (is_string($value)) {
                $clean[$key] = mb_scrub($value, 'UTF-8');
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
