<?php

namespace Webkul\Security\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Security\Models\SecurityAuditLog;

final class SecurityAuditRecorder
{
    private const REDACT_PATTERN = '/(password|secret|token|api_key|private_key|authorization|cookie|recovery_code)/i';

    private static bool $tableVerified = false;

    /**
     * Append a sanitized, immutable security audit event without disrupting caller flow if schema is pre-install.
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
            if (! self::$tableVerified) {
                if (! Schema::hasTable('security_audit_logs')) {
                    return;
                }

                self::$tableVerified = true;
            }

            $user = auth()->guard('user')->user();
            $resolvedActorId = $actorId ?? ($user ? (int) $user->getAuthIdentifier() : null);
            $resolvedActorEmail = $actorEmail ?? ($user->email ?? null);
            $req = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            SecurityAuditLog::create([
                'actor_id' => $resolvedActorId,
                'actor_email' => self::cleanUtf8($resolvedActorEmail !== null ? (string) $resolvedActorEmail : null, 191),
                'event' => self::cleanUtf8($event, 96) ?? 'unknown',
                'auditable_type' => self::cleanUtf8($auditableType, 96),
                'auditable_id' => self::cleanUtf8($auditableId !== null ? (string) $auditableId : null, 64),
                'ip_address' => self::cleanUtf8($req?->ip() ? (string) $req->ip() : null, 45),
                'user_agent' => self::cleanUtf8($req?->userAgent() ? (string) $req->userAgent() : null, 255),
                'metadata' => $metadata !== null ? self::sanitizeMetadata($metadata) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            if (self::$tableVerified) {
                report($e);
            }
        }
    }

    public static function resetTableCache(): void
    {
        self::$tableVerified = false;
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
