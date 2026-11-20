<?php

namespace Webkul\Workspace;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Models\WorkspaceMember;

/**
 * Decides which database the application talks to.
 *
 * Every business has its own database. Switching replaces the database name on the default
 * connection, so models, repositories, datagrids and raw queries all follow without knowing
 * about workspaces. Separation therefore does not depend on any query remembering a filter.
 * The cache prefix, the file disks and the resolved sign-in are switched with it.
 */
class WorkspaceManager
{
    public const LANDLORD = 'landlord';

    private ?Workspace $current = null;

    private string $connection;

    private string $landlordDatabase;

    private string $cachePrefix;

    /** @var array<string, array{root: string, url: string|null}> */
    private array $disks = [];

    public function __construct()
    {
        $this->connection = (string) config('database.default');
        $this->landlordDatabase = (string) config("database.connections.{$this->connection}.database");
        $this->cachePrefix = (string) config('cache.prefix');

        foreach (['local', 'public'] as $disk) {
            if (is_string($root = config("filesystems.disks.{$disk}.root"))) {
                $url = config("filesystems.disks.{$disk}.url");

                $this->disks[$disk] = ['root' => $root, 'url' => is_string($url) ? $url : null];
            }
        }

        config(['database.connections.'.self::LANDLORD => config("database.connections.{$this->connection}")]);
    }

    public function enabled(): bool
    {
        return (bool) config('workspaces.enabled');
    }

    public function current(): ?Workspace
    {
        return $this->current;
    }

    public function landlordDatabase(): string
    {
        return $this->landlordDatabase;
    }

    /**
     * Point the application at one workspace's database.
     */
    public function switchTo(Workspace $workspace): void
    {
        if (! is_string($workspace->database) || $workspace->database === '') {
            throw new InvalidArgumentException("Workspace [{$workspace->slug}] has no database yet.");
        }

        $this->apply($workspace->database, 'ws'.$workspace->id, 'workspaces/'.$workspace->id);

        $this->current = $workspace;
    }

    /**
     * Point the application back at the main database.
     */
    public function switchToLandlord(): void
    {
        if ($this->current === null) {
            return;
        }

        $this->apply($this->landlordDatabase, null, null);

        $this->current = null;
    }

    /**
     * Run a callback inside a workspace, then return to wherever the caller was.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(?Workspace $workspace, Closure $callback): mixed
    {
        $previous = $this->current;

        $workspace === null ? $this->switchToLandlord() : $this->switchTo($workspace);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->switchToLandlord() : $this->switchTo($previous);
        }
    }

    /**
     * The workspace an email address signs in to, if it belongs to one.
     */
    public function findByEmail(string $email): ?Workspace
    {
        $member = WorkspaceMember::query()->where('email', self::normaliseEmail($email))->first();

        if ($member === null) {
            return null;
        }

        return Workspace::query()->active()->find($member->workspace_id);
    }

    /**
     * Whether an email address already signs in somewhere other than the given workspace.
     * One address belongs to exactly one workspace, otherwise sign-in would be ambiguous.
     */
    public function emailTakenElsewhere(string $email, ?Workspace $workspace): bool
    {
        $email = self::normaliseEmail($email);

        $member = WorkspaceMember::query()->where('email', $email)->first();

        if ($member !== null && ($workspace === null || (int) $member->workspace_id !== (int) $workspace->id)) {
            return true;
        }

        if ($workspace === null) {
            return false;
        }

        return DB::connection(self::LANDLORD)->table('users')->where('email', $email)->exists();
    }

    /**
     * The folders that hold one workspace's uploaded files.
     *
     * @return list<string>
     */
    public function diskRootsFor(Workspace $workspace): array
    {
        return array_values(array_map(
            static fn (array $base): string => $base['root'].'/workspaces/'.$workspace->id,
            $this->disks
        ));
    }

    /**
     * Whether a new owner must open an emailed link before their workspace is built. Unless set
     * explicitly it follows the sign-up mode: required when anyone may sign up, not required
     * when an invite code already vouches for them.
     */
    public function verifiesEmail(): bool
    {
        $setting = config('workspaces.verify_email');

        /**
         * An empty value, which is what an unset variable looks like when passed through
         * Docker Compose, counts as not set.
         */
        if ($setting === null || $setting === '') {
            return config('workspaces.signup') === 'open';
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public function databaseNameFor(Workspace $workspace): string
    {
        return $this->landlordDatabase.'_ws_'.$workspace->id;
    }

    public function createDatabase(string $name): void
    {
        DB::connection(self::LANDLORD)->statement(
            'CREATE DATABASE '.self::quoteIdentifier($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public function dropDatabase(string $name): void
    {
        if ($name === $this->landlordDatabase || ! str_starts_with($name, $this->landlordDatabase.'_ws_')) {
            throw new InvalidArgumentException("Refusing to drop [{$name}]: it is not a workspace database.");
        }

        DB::connection(self::LANDLORD)->statement('DROP DATABASE IF EXISTS '.self::quoteIdentifier($name));
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private static function quoteIdentifier(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Database names may only contain letters, digits and underscores.');
        }

        return '`'.$name.'`';
    }

    private function apply(string $database, ?string $cacheSuffix, ?string $diskSuffix): void
    {
        config(["database.connections.{$this->connection}.database" => $database]);

        DB::purge($this->connection);

        /**
         * A signed-in user resolved from the previous database must not be carried into this one.
         */
        app('auth')->forgetGuards();

        $prefix = $cacheSuffix === null ? $this->cachePrefix : $this->cachePrefix.'_'.$cacheSuffix;

        config(['cache.prefix' => $prefix]);

        $store = Cache::getStore();

        if (method_exists($store, 'setPrefix')) {
            $store->setPrefix($prefix);
        }

        foreach ($this->disks as $disk => $base) {
            config(["filesystems.disks.{$disk}.root" => $diskSuffix === null ? $base['root'] : $base['root'].'/'.$diskSuffix]);

            if ($base['url'] !== null) {
                config(["filesystems.disks.{$disk}.url" => $diskSuffix === null ? $base['url'] : $base['url'].'/'.$diskSuffix]);
            }

            Storage::forgetDisk($disk);
        }
    }
}
