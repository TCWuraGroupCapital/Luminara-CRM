<?php

namespace Webkul\Security\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webkul\Installer\Helpers\DatabaseManager;

class DeepHealthCheckListener
{
    /**
     * Perform deep DB, cache, and storage readiness checks on /up once installation has completed or in production.
     */
    public function handle(DiagnosingHealth $event): void
    {
        /** @var DatabaseManager $dbManager */
        $dbManager = app(DatabaseManager::class);

        $mustEnforceReadiness = file_exists(storage_path('installed'))
            || app()->isProduction()
            || $dbManager->isInstallationCompleted();

        if (! $mustEnforceReadiness) {
            return;
        }

        // 1. Database connectivity & query execution probe
        DB::connection()->getPdo();
        DB::select('SELECT 1');

        // 2. Default Cache store read/write round-trip probe
        $probeKey = 'luminara:health:'.bin2hex(random_bytes(6));
        Cache::store()->put($probeKey, '1', 10);
        $cached = Cache::store()->get($probeKey);
        Cache::store()->forget($probeKey);

        if ($cached !== '1') {
            throw new RuntimeException('Cache store readiness probe failed.');
        }

        // 3. Local framework storage directory writability probe
        $frameworkDir = storage_path('framework');

        if (! is_dir($frameworkDir) || ! is_writable($frameworkDir)) {
            throw new RuntimeException('Storage framework directory is not writable.');
        }
    }
}
