<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Models\CoreConfig;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\Security\Models\UserMfaSetting;
use Webkul\Security\Support\CoreConfigEncryptor;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\Security\Support\Totp;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('matches RFC 6238 SHA-1 TOTP test vectors and enforces strict monotonic replay protection', function () {
    // RFC 6238 Appendix B 20-byte ASCII seed "12345678901234567890"
    $rfcBase32 = Totp::encodeBase32('12345678901234567890');

    expect($rfcBase32)->toBe('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')
        ->and(Totp::decodeBase32($rfcBase32))->toBe('12345678901234567890')
        ->and(Totp::codeAt($rfcBase32, intdiv(59, 30)))->toBe('287082')
        ->and(Totp::codeAt($rfcBase32, intdiv(1111111109, 30)))->toBe('081804');

    $secret = Totp::generateSecret();
    $now = 1700000100;
    $slice = intdiv($now, 30);
    $code = Totp::codeAt($secret, $slice);

    $matched = Totp::verify($secret, $code, null, $now);
    expect($matched)->toBe($slice);

    // Replaying the exact same code within the same or earlier time slice MUST fail
    expect(Totp::verify($secret, $code, $matched, $now))->toBeNull();
});

it('enforces end-to-end Admin TOTP MFA enrollment, challenge gating, single-use recovery codes, and custom-role self-service access', function () {
    $role = Role::create([
        'name' => 'MFA Custom Rep '.bin2hex(random_bytes(4)),
        'description' => 'Sales rep without settings ACL',
        'permission_type' => 'custom',
        'permissions' => ['dashboard'],
    ]);

    $user = User::create([
        'name' => 'MFA Protected Rep',
        'email' => 'mfa-rep-'.bin2hex(random_bytes(4)).'@luminaracrm.local',
        'password' => bcrypt('CorrectHorseBattery!99'),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => $role->id,
    ]);

    // 1. Custom-role user without settings permission sees MFA card on My Account and can open MFA setup, but NOT audit logs
    test()->actingAs($user, 'user')
        ->get(route('admin.user.account.edit'))
        ->assertOk()
        ->assertSee(route('admin.security.mfa.setup'), false);

    test()->actingAs($user, 'user')
        ->get(route('admin.security.audit_logs.index'))
        ->assertStatus(401);

    $setupResponse = test()->actingAs($user, 'user')
        ->get(route('admin.security.mfa.setup'))
        ->assertOk();

    $pendingSecret = (string) session('security.mfa_pending_secret');
    expect($pendingSecret)->toHaveLength(32)
        ->and($setupResponse->getContent())->toContain(trim(chunk_split($pendingSecret, 4, ' ')));

    // 2. Enroll with valid password + current TOTP code
    $validCode = Totp::codeAt($pendingSecret, intdiv(time(), 30));

    $enableJson = test()->actingAs($user, 'user')
        ->postJson(route('admin.security.mfa.enable'), [
            'current_password' => 'CorrectHorseBattery!99',
            'code' => $validCode,
        ])
        ->assertOk()
        ->json();

    expect($enableJson['enabled'])->toBeTrue()
        ->and($enableJson['recovery_codes'])->toHaveCount(8);

    $recoveryCode = $enableJson['recovery_codes'][0];

    // 3. Simulate fresh login (clears MFA_SESSION_KEY): dashboard redirects to challenge; JSON API returns 403
    session()->forget('security.mfa_verified_user_id');

    test()->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertRedirect(route('admin.security.mfa.challenge'));

    test()->actingAs($user, 'user')
        ->get(route('admin.security.mfa.challenge'))
        ->assertOk()
        ->assertSee(trans('admin::app.layouts.sign-out'))
        ->assertDontSee('admin::app.');

    test()->actingAs($user, 'user')
        ->getJson(route('admin.dashboard.index'))
        ->assertStatus(403)
        ->assertJsonPath('mfa_required', true);

    // Attacker with stolen password also cannot bypass challenge on MFA setup or non-/admin authenticated staff routes
    test()->actingAs($user, 'user')
        ->get(route('admin.security.mfa.setup'))
        ->assertRedirect(route('admin.security.mfa.challenge'));

    test()->actingAs($user, 'user')
        ->get('/web-forms/form/1/form.html')
        ->assertRedirect(route('admin.security.mfa.challenge'));

    // 4. Replaying the enrollment TOTP slice must be rejected by monotonic replay guard
    test()->actingAs($user, 'user')
        ->postJson(route('admin.security.mfa.verify'), ['code' => $validCode])
        ->assertStatus(422);

    // 5. Single-use recovery code unlocks session once, neutralizes off-origin url.intended, and cannot be replayed
    session()->put('url.intended', 'https://evil.example/admin/leads');

    test()->actingAs($user, 'user')
        ->postJson(route('admin.security.mfa.verify'), ['code' => $recoveryCode])
        ->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('redirect', route('admin.dashboard.index'));

    // Forced-MFA policy blocks disabling MFA
    config(['security.require_admin_mfa' => true]);

    test()->actingAs($user, 'user')
        ->postJson(route('admin.security.mfa.disable'), [
            'current_password' => 'CorrectHorseBattery!99',
            'code' => $enableJson['recovery_codes'][1],
        ])
        ->assertStatus(422);

    config(['security.require_admin_mfa' => false]);

    expect(UserMfaSetting::where('user_id', $user->id)->first()->recovery_codes_hashes)->toHaveCount(7);

    session()->forget('security.mfa_verified_user_id');

    test()->actingAs($user, 'user')
        ->postJson(route('admin.security.mfa.verify'), ['code' => $recoveryCode])
        ->assertStatus(422);
});

it('throttles distributed credential stuffing targeting one account across rotating botnet IPs', function () {
    config(['security.max_failed_logins_per_email' => 6]);

    $victimEmail = 'ceo-'.bin2hex(random_bytes(4)).'@luminaracrm.local';
    $admin = getDefaultAdmin();

    User::create([
        'name' => 'CEO Target',
        'email' => $victimEmail,
        'password' => bcrypt('UltraStrongVaultKey!2026'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $admin->role_id,
    ]);

    for ($i = 1; $i <= 6; $i++) {
        test()->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(route('admin.session.store'), [
                'email' => $victimEmail,
                'password' => "wrong-attempt-{$i}",
            ])
            ->assertStatus(302);
    }

    // 7th and 8th attempts from brand-new IPs are blocked with 429 while deduplicating lockout audit rows
    foreach (['198.51.100.250', '198.51.100.251'] as $botIp) {
        test()->withServerVariables(['REMOTE_ADDR' => $botIp])
            ->post(route('admin.session.store'), [
                'email' => $victimEmail,
                'password' => 'wrong-attempt-flood',
            ])
            ->assertStatus(429);
    }

    expect(SecurityAuditLog::where('event', 'auth.lockout')->where('actor_email', $victimEmail)->count())->toBe(1);
});

it('encrypts sensitive core_config secrets at rest while returning decrypted plaintext via core()->getConfigData()', function () {
    $apiKey = 'sk-or-v1-live-secret-'.bin2hex(random_bytes(8));
    $imapPass = 'ImapMailboxSecret!'.bin2hex(random_bytes(6));

    CoreConfig::updateOrCreate(
        ['code' => 'general.magic_ai.settings.api_key'],
        ['value' => $apiKey]
    );

    CoreConfig::updateOrCreate(
        ['code' => 'email.imap.account.password'],
        ['value' => $imapPass]
    );

    $rawApiInDb = (string) DB::table('core_config')
        ->where('code', 'general.magic_ai.settings.api_key')
        ->value('value');

    $rawImapInDb = (string) DB::table('core_config')
        ->where('code', 'email.imap.account.password')
        ->value('value');

    expect($rawApiInDb)->toStartWith(CoreConfigEncryptor::PREFIX)
        ->and($rawApiInDb)->not->toContain($apiKey)
        ->and($rawImapInDb)->toStartWith(CoreConfigEncryptor::PREFIX)
        ->and($rawImapInDb)->not->toContain($imapPass)
        ->and(core()->getConfigData('general.magic_ai.settings.api_key'))->toBe($apiKey)
        ->and(core()->getConfigData('email.imap.account.password'))->toBe($imapPass);
});

it('enforces append-only immutability, multibyte UTF-8 safety, and compound secret redaction on security_audit_logs', function () {
    SecurityAuditRecorder::resetTableCache();

    // Multibyte character straddling byte 255 must be scrubbed/truncated cleanly by mb_substr without dropping the audit row
    $multibyteUa = str_repeat('A', 254).'é-overflow';
    request()->headers->set('User-Agent', $multibyteUa);

    SecurityAuditRecorder::record('test.sensitive_action', 'user', 1, [
        'email' => 'ops@luminaracrm.local',
        'password' => 'SuperSecretShouldRedact',
        'api_key' => 'sk-redact-me',
        'client_secret' => 'oauth-secret',
        'inbound_token' => 'webhook-token',
    ]);

    $log = SecurityAuditLog::query()->where('event', 'test.sensitive_action')->latest('id')->firstOrFail();

    expect($log->metadata['email'])->toBe('ops@luminaracrm.local')
        ->and($log->metadata['password'])->toBe('[REDACTED]')
        ->and($log->metadata['api_key'])->toBe('[REDACTED]')
        ->and($log->metadata['client_secret'])->toBe('[REDACTED]')
        ->and($log->metadata['inbound_token'])->toBe('[REDACTED]')
        ->and(mb_strlen((string) $log->user_agent, 'UTF-8'))->toBe(255);

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});

it('runs deep /up readiness checks post-install and returns 500 if cache or DB probe fails', function () {
    $installedMarker = storage_path('installed');
    $hadMarker = file_exists($installedMarker);

    if (! $hadMarker) {
        file_put_contents($installedMarker, 'test-installed');
    }

    try {
        // Even if DatabaseManager::isInstallationCompleted() returns false (e.g., during a DB outage),
        // the presence of storage/installed forces /up to run deep readiness probes and fail closed!
        app()->instance(DatabaseManager::class, new class extends DatabaseManager
        {
            public function isInstallationCompleted(): bool
            {
                return false;
            }
        });

        test()->get('/up')->assertOk();

        Cache::shouldReceive('store')->once()->andThrow(new RuntimeException('Redis connection refused'));

        test()->get('/up')->assertStatus(500);
    } finally {
        if (! $hadMarker && file_exists($installedMarker)) {
            @unlink($installedMarker);
        }
    }
});

it('rejects weak credentials in luminara:provision and verifies compressed backups with tamper detection', function () {
    Storage::fake('local');

    // Weak password or default admin@example.com must fail closed
    expect(Artisan::call('luminara:provision', [
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'StrongEnoughPass!2026',
    ]))->toBe(1);

    expect(Artisan::call('luminara:provision', [
        '--admin-email' => 'security-lead@luminaracrm.local',
        '--admin-password' => 'admin123',
    ]))->toBe(1);

    $provisionEmail = 'enterprise-ops-'.bin2hex(random_bytes(4)).'@luminaracrm.local';

    expect(Artisan::call('luminara:provision', [
        '--admin-email' => $provisionEmail,
        '--admin-password' => 'VaultGradeAdminPass!2026',
    ]))->toBe(0);

    expect(User::where('email', $provisionEmail)->exists())->toBeTrue();

    // Create logical backup and verify SHA-256 + HMAC + row counts
    expect(Artisan::call('luminara:backup', ['--disk' => 'local', '--path' => 'test-backups']))->toBe(0);
    expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'test-backups']))->toBe(0);

    // Stripping hmac_sha256 from manifest must fail closed
    $manifestFile = collect(Storage::disk('local')->files('test-backups'))
        ->first(fn (string $f) => str_ends_with($f, '.manifest.json'));
    $originalManifest = Storage::disk('local')->get($manifestFile);
    $stripped = json_decode($originalManifest, true);
    unset($stripped['hmac_sha256']);
    Storage::disk('local')->put($manifestFile, json_encode($stripped));

    expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'test-backups']))->toBe(1);

    // Restore manifest and tamper with archive bytes -> backup-verify must fail with exit code 1
    Storage::disk('local')->put($manifestFile, $originalManifest);

    $archive = collect(Storage::disk('local')->files('test-backups'))
        ->first(fn (string $f) => str_ends_with($f, '.ndjson.gz'));

    Storage::disk('local')->put($archive, 'corrupted-gzip-bytes');

    expect(Artisan::call('luminara:backup-verify', ['--disk' => 'local', '--path' => 'test-backups']))->toBe(1);
});

it('enforces 10-locale translation parity across Webkul\\Security language files', function () {
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];
    $enKeys = array_keys(Arr::dot(require base_path('packages/Webkul/Security/src/Resources/lang/en/app.php')));

    expect($enKeys)->not->toBeEmpty();

    foreach ($locales as $locale) {
        $path = base_path("packages/Webkul/Security/src/Resources/lang/{$locale}/app.php");
        expect(is_file($path))->toBeTrue("Missing Security lang file for [{$locale}]");

        $translated = Arr::dot(require $path);

        foreach ($enKeys as $key) {
            expect($translated)->toHaveKey($key)
                ->and($translated[$key])->toBeString()->not->toBeEmpty();
        }
    }
});
