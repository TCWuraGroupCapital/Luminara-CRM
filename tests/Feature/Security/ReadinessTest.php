<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Security\Console\Commands\OpsAlertCommand;
use Webkul\Security\Logging\RedactingJsonFormatter;
use Webkul\Security\Support\BackupAge;
use Webkul\Security\Support\QueueHeartbeat;
use Webkul\Security\Support\ReadinessProbe;
use Webkul\Security\Support\SentryGate;

beforeEach(function () {
    $boot = sys_get_temp_dir().'/luminara-health-boot-'.bin2hex(random_bytes(4));
    file_put_contents($boot, (string) time());
    $backupDir = 'backups-health-'.bin2hex(random_bytes(4));
    Storage::disk('local')->makeDirectory($backupDir);

    config([
        'security.health_boot_path' => $boot,
        'security.backup_path' => $backupDir,
        'security.health_ready_rate_per_minute' => 120,
        'security.health_queue_max_age_seconds' => 180,
        'security.health_queue_start_grace_seconds' => 180,
        'security.health_backup_max_age_seconds' => 93600,
        'security.health_backup_critical' => false,
        'security.health_ledger_max_age_seconds' => 86400 * 365 * 20,
        'security.health_ledger_critical' => false,
        'security.alert_email' => '',
        'security.alert_slack_webhook' => '',
    ]);

    Cache::forget(QueueHeartbeat::CACHE_KEY);
    Cache::forget(OpsAlertCommand::FAILED_JOBS_KEY);
    Cache::forget(OpsAlertCommand::BACKUP_ALERT_KEY);
    Cache::store('file')->flush();

    bindReadinessRedis(true);
});

afterEach(function () {
    $boot = (string) config('security.health_boot_path');

    if ($boot !== '' && str_contains($boot, 'luminara-health-boot-') && is_file($boot)) {
        @unlink($boot);
    }

    $dir = (string) config('security.backup_path');

    if (str_starts_with($dir, 'backups-health-')) {
        Storage::disk('local')->deleteDirectory($dir);
    }
});

it('reports every check as ok when dependencies are fresh', function () {
    plantBackupManifest(60);
    app(QueueHeartbeat::class)->record();

    $response = $this->get('/health/ready');

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $response
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.database.critical', true)
        ->assertJsonPath('checks.redis.status', 'ok')
        ->assertJsonPath('checks.redis.critical', true)
        ->assertJsonPath('checks.queue.status', 'ok')
        ->assertJsonPath('checks.queue.critical', true)
        ->assertJsonPath('checks.backup.status', 'ok')
        ->assertJsonPath('checks.ledger_seal.status', 'ok');

    expect($response->json('checks.database.latency_ms'))->toBeInt()
        ->and($response->json('checks.queue.age_seconds'))->toBeLessThan(5)
        ->and($response->json('checks.backup.age_seconds'))->toBeGreaterThan(50)
        ->and($response->json('checks.backup.completed_at'))->toBeString();
});

it('fails the database check without leaking the connection secret', function () {
    $secret = 'super-secret-db-password-value';
    $port = config('database.connections.mysql.port');
    config([
        'database.connections.mysql.password' => $secret,
        'database.connections.mysql.port' => '1',
    ]);
    DB::purge('mysql');

    try {
        $response = $this->get('/health/ready');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('checks.database.status', 'fail')
            ->assertJsonPath('checks.database.error', 'unavailable');

        expect($response->getContent())->not->toContain($secret);
    } finally {
        config(['database.connections.mysql.port' => $port]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }
});

it('does not copy an exception message into the database check', function () {
    $secret = 'super-secret-db-password-value';
    config(['database.connections.mysql.password' => $secret]);

    $probe = new class(app(QueueHeartbeat::class), app(BackupAge::class), $secret) extends ReadinessProbe
    {
        public function __construct(QueueHeartbeat $heartbeat, BackupAge $backups, private string $secret)
        {
            parent::__construct($heartbeat, $backups);
        }

        protected function pingDatabase(): void
        {
            throw new RuntimeException('login failed for '.$this->secret);
        }

        protected function pingRedis(): bool
        {
            return true;
        }
    };

    $check = $probe->checkDatabase();

    expect($check['status'])->toBe('fail')
        ->and($check['error'])->toBe('unavailable')
        ->and(json_encode($check))->not->toContain($secret);
});

it('fails the redis check when ping does not answer', function () {
    bindReadinessRedis(false);
    config(['database.redis.default.password' => 'super-secret-redis-password-value']);
    app(QueueHeartbeat::class)->record();
    plantBackupManifest(60);

    $response = $this->get('/health/ready');

    $response->assertStatus(503)
        ->assertJsonPath('checks.redis.status', 'fail')
        ->assertJsonPath('checks.redis.error', 'unavailable');

    expect($response->getContent())->not->toContain('super-secret-redis-password-value');
});

it('fails closed when the real redis connection is down', function () {
    $this->app->forgetInstance(ReadinessProbe::class);
    config([
        'database.redis.default.host' => '127.0.0.1',
        'database.redis.default.port' => '1',
        'database.redis.default.timeout' => 0.2,
        'database.redis.default.password' => 'super-secret-redis-password-value',
    ]);
    Redis::purge('default');

    $response = $this->get('/health/ready');

    $response->assertStatus(503)
        ->assertJsonPath('checks.redis.status', 'fail')
        ->assertJsonPath('checks.redis.error', 'unavailable');

    expect($response->getContent())->not->toContain('super-secret-redis-password-value');
});

it('accepts redis pong replies', function () {
    expect(ReadinessProbe::acceptsPong(true))->toBeTrue()
        ->and(ReadinessProbe::acceptsPong('PONG'))->toBeTrue()
        ->and(ReadinessProbe::acceptsPong('+PONG'))->toBeTrue()
        ->and(ReadinessProbe::acceptsPong('pong'))->toBeTrue()
        ->and(ReadinessProbe::acceptsPong(false))->toBeFalse()
        ->and(ReadinessProbe::acceptsPong('NO'))->toBeFalse();
});

it('reports a fresh queue heartbeat as ok', function () {
    $stamp = app(QueueHeartbeat::class)->record();

    $check = app(ReadinessProbe::class)->checkQueue();

    expect($check['status'])->toBe('ok')
        ->and($check['critical'])->toBeTrue()
        ->and($check['heartbeat_at'])->toBe($stamp)
        ->and($check['age_seconds'])->toBeLessThan(5);
});

it('reports a missing heartbeat as starting during the boot grace', function () {
    $response = $this->get('/health/ready');

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'starting')
        ->assertJsonPath('checks.queue.heartbeat_at', null);
});

it('repairs a corrupt queue grace file instead of staying in grace forever', function () {
    $path = (string) config('security.health_boot_path');
    file_put_contents($path, '0');

    $check = app(ReadinessProbe::class)->checkQueue();

    expect($check['status'])->toBe('starting')
        ->and((int) file_get_contents($path))->toBeGreaterThan(time() - 5);

    file_put_contents($path, (string) (time() - 10000));
    config(['security.health_queue_start_grace_seconds' => 0]);

    expect(app(ReadinessProbe::class)->checkQueue()['status'])->toBe('fail')
        ->and(app(ReadinessProbe::class)->checkQueue()['error'])->toBe('missing');
});

it('fails a missing heartbeat immediately when the start grace is zero', function () {
    $path = (string) config('security.health_boot_path');
    @unlink($path);
    config(['security.health_queue_start_grace_seconds' => 0]);

    $check = app(ReadinessProbe::class)->checkQueue();

    expect($check['status'])->toBe('fail')
        ->and($check['error'])->toBe('missing');
});

it('returns 503 when the queue heartbeat is stale after the start grace', function () {
    config(['security.health_queue_start_grace_seconds' => 0]);
    Cache::forever(QueueHeartbeat::CACHE_KEY, gmdate('Y-m-d\TH:i:s\Z', time() - 1000));
    plantBackupManifest(60);

    $response = $this->get('/health/ready');

    $response->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.queue.status', 'fail')
        ->assertJsonPath('checks.queue.error', 'stale');
});

it('records a queue heartbeat from the scheduled command', function () {
    $this->artisan('luminara:queue-heartbeat')->assertSuccessful();

    expect(app(QueueHeartbeat::class)->read())->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');

    config([
        'security.health_queue_start_grace_seconds' => 0,
        'security.health_queue_max_age_seconds' => 30,
    ]);
    Cache::forever(QueueHeartbeat::CACHE_KEY, gmdate('Y-m-d\TH:i:s\Z', time() - 1000));

    $this->artisan('luminara:queue-heartbeat', ['--check' => true])->assertFailed();
});

it('reports a fresh backup and a stale backup', function () {
    plantBackupManifest(120);

    $fresh = app(ReadinessProbe::class)->checkBackup();

    expect($fresh['status'])->toBe('ok')
        ->and($fresh['critical'])->toBeFalse()
        ->and($fresh['age_seconds'])->toBeGreaterThan(100)
        ->and($fresh['completed_at'])->toBeString();

    Storage::disk('local')->deleteDirectory((string) config('security.backup_path'));
    plantBackupManifest(200000);
    config(['security.health_backup_critical' => true]);

    $stale = app(ReadinessProbe::class)->checkBackup();

    expect($stale['status'])->toBe('fail')
        ->and($stale['error'])->toBe('stale')
        ->and($stale['critical'])->toBeTrue();

    $response = $this->get('/health/ready');

    $response->assertStatus(503);
    expect($response->getContent())->not->toContain('super-secret-backup-hmac-value');
});

it('reports a missing backup as degraded unless it is marked critical', function () {
    $missing = app(ReadinessProbe::class)->checkBackup();

    expect($missing['status'])->toBe('fail')
        ->and($missing['error'])->toBe('missing')
        ->and($missing['critical'])->toBeFalse();

    app(QueueHeartbeat::class)->record();

    $this->get('/health/ready')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.backup.error', 'missing');
});

it('reports ledger seal age and fails when an unsealed block is too old', function () {
    $root = bin2hex(random_bytes(32));
    $blockId = null;
    $epochId = DB::table('ledger_epochs')->insertGetId([
        'start_height' => 1,
        'end_height' => 1,
        'block_count' => 1,
        'merkle_root' => $root,
        'prev_epoch_root' => str_repeat('0', 64),
        'server_signature' => 'test-signature',
        'anchor_driver' => 'local',
        'sealed_at' => now()->subMinutes(10),
    ]);

    try {
        $fresh = app(ReadinessProbe::class)->checkLedger();

        expect($fresh)->not->toBeNull()
            ->and($fresh['status'])->toBe('ok')
            ->and($fresh['sealed_at'])->toBeString()
            ->and(json_encode($fresh))->not->toContain($root);

        $blockId = DB::table('data_blocks')->insertGetId([
            'block_height' => random_int(800000000, 899999999),
            'receipt_code' => 'LUM-'.bin2hex(random_bytes(8)),
            'prev_block_hash' => str_repeat('a', 64),
            'block_hash' => bin2hex(random_bytes(32)),
            'event_name' => 'health.test',
            'entity_type' => 'health',
            'entity_id' => '1',
            'actor_kind' => 'system',
            'canonical_payload' => '{}',
            'signer_alg' => 'ed25519',
            'signer_pubkey' => 'test',
            'signer_proof' => 'test',
            'epoch_id' => null,
            'created_at' => now()->subDays(3),
        ]);

        config(['security.health_ledger_max_age_seconds' => 3600]);

        $stale = app(ReadinessProbe::class)->checkLedger();

        expect($stale['status'])->toBe('fail')
            ->and($stale['error'])->toBe('stale')
            ->and($stale['critical'])->toBeFalse();
    } finally {
        if ($blockId !== null) {
            DB::table('data_blocks')->where('id', $blockId)->delete();
        }

        DB::table('ledger_epochs')->where('id', $epochId)->delete();
    }
});

it('omits the ledger check when that feature is not installed', function () {
    $probe = new class(app(QueueHeartbeat::class), app(BackupAge::class)) extends ReadinessProbe
    {
        public function __construct(QueueHeartbeat $heartbeat, BackupAge $backups)
        {
            parent::__construct($heartbeat, $backups);
        }

        protected function pingRedis(): bool
        {
            return true;
        }

        protected function ledgerFeatureExists(): bool
        {
            return false;
        }
    };

    expect($probe->checkLedger())->toBeNull()
        ->and($probe->run()['checks'])->not->toHaveKey('ledger_seal');
});

it('rate limits the readiness endpoint', function () {
    config(['security.health_ready_rate_per_minute' => 2]);
    Cache::store('file')->flush();
    app(QueueHeartbeat::class)->record();
    plantBackupManifest(30);

    $this->get('/health/ready')->assertOk();
    $this->get('/health/ready')->assertOk();

    $this->get('/health/ready')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Too many requests.');
});

it('answers the readiness route before installation instead of redirecting', function () {
    $reached = false;
    $response = (new CanInstall)->handle(Request::create('/health/ready', 'GET'), function () use (&$reached) {
        $reached = true;

        return response('ok', 200);
    });

    expect($reached)->toBeTrue()
        ->and($response->getStatusCode())->toBe(200);
});

it('selects the json log channel from config', function () {
    expect(config('logging.channels.json.formatter'))->toBe(RedactingJsonFormatter::class)
        ->and(config('logging.channels.json.driver'))->toBe('monolog');
});

it('keeps sentry off unless SENTRY_LARAVEL_DSN is set', function () {
    expect(config('sentry.dsn'))->toBeNull()
        ->and(config('sentry.spotlight'))->toBeFalse()
        ->and(config('sentry.send_default_pii'))->toBeFalse()
        ->and(SentryGate::enabled())->toBeFalse()
        ->and(app('sentry')->getClient()->getOptions()->getDsn())->toBeNull()
        ->and(file_get_contents(config_path('sentry.php')))->not->toContain("env('SENTRY_DSN'");

    config(['sentry.dsn' => 'https://example.invalid/not-a-real-dsn']);

    expect(SentryGate::enabled())->toBeTrue();

    config(['sentry.dsn' => '   ']);

    expect(SentryGate::enabled())->toBeFalse();

    config(['sentry.dsn' => null]);
});

it('schedules the heartbeat and the operational alert', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command)
        ->implode("\n");

    expect($commands)->toContain('luminara:queue-heartbeat')
        ->and($commands)->toContain('luminara:ops-alert');
});

it('alerts when failed jobs grow and does not include the payload', function () {
    plantBackupManifest(30);
    $secret = 'super-secret-job-payload-value';
    $seen = [];

    Log::listen(function ($event) use (&$seen) {
        $seen[] = $event;
    });

    $this->artisan('luminara:ops-alert', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"alerts":[]');

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{"displayName":"'.$secret.'"}',
        'exception' => 'RuntimeException: '.$secret,
        'failed_at' => now(),
    ]);

    $this->artisan('luminara:ops-alert', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('failed_jobs_grew');

    $output = collect($seen)
        ->filter(fn ($event) => $event->message === 'luminara.ops.alert')
        ->map(fn ($event) => json_encode($event->context))
        ->implode("\n");

    expect($output)->toContain('failed_jobs_grew')
        ->and($output)->not->toContain($secret);

    DB::table('failed_jobs')->where('payload', 'like', '%'.$secret.'%')->delete();
});

it('alerts on a stale backup by log, mail, and slack webhook without repeating during the cooldown', function () {
    plantBackupManifest(200000);
    config([
        'security.alert_email' => 'ops-alerts@example.com',
        'security.alert_slack_webhook' => 'https://hooks.example.invalid/services/test-hook',
        'security.alert_backup_cooldown_seconds' => 3600,
    ]);

    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $transport->flush();

    Http::fake([
        'https://hooks.example.invalid/*' => Http::response('ok', 200),
    ]);

    $this->artisan('luminara:ops-alert', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('backup_stale');

    $raw = $transport->messages()->map(function ($message) {
        return $message->getOriginalMessage()->toString();
    })->implode("\n");

    expect($raw)->toContain('ops-alerts@example.com')
        ->and($raw)->toContain('newest successful backup')
        ->and($raw)->not->toContain('hooks.example.invalid');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.example.invalid/services/test-hook'
            && str_contains((string) $request['text'], 'newest successful backup')
            && ! str_contains((string) $request['text'], 'hooks.example.invalid');
    });

    $this->artisan('luminara:ops-alert', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"alerts":[]');
});

it('does not email or call slack when those settings are empty', function () {
    plantBackupManifest(200000);

    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $transport->flush();
    Http::fake();

    $this->artisan('luminara:ops-alert', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('backup_stale');

    expect($transport->messages())->toHaveCount(0);
    Http::assertNothingSent();
});

function bindReadinessRedis(bool $ok): void
{
    $probe = new class(app(QueueHeartbeat::class), app(BackupAge::class), $ok) extends ReadinessProbe
    {
        public function __construct(QueueHeartbeat $heartbeat, BackupAge $backups, private bool $ok)
        {
            parent::__construct($heartbeat, $backups);
        }

        protected function pingRedis(): bool
        {
            if (! $this->ok) {
                throw new RuntimeException('redis unavailable super-secret-redis-password-value');
            }

            return true;
        }
    };

    app()->instance(ReadinessProbe::class, $probe);
}

function plantBackupManifest(int $ageSeconds): void
{
    $when = time() - $ageSeconds;
    $name = gmdate('Ymd_His', $when);
    Storage::disk('local')->put(
        config('security.backup_path').'/luminara_backup_'.$name.'.manifest.json',
        '{"hmac_sha256":"super-secret-backup-hmac-value"}'
    );
}
