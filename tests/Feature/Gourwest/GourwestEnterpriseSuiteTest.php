<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Webkul\Contact\Models\Person;
use Webkul\Experiment\Models\Experiment;
use Webkul\Gourwest\Models\CustomerProfile;
use Webkul\Gourwest\Models\Integration;
use Webkul\Gourwest\Models\ServiceTicket;
use Webkul\Gourwest\Models\SyncEvent;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    SecurityAuditRecorder::resetTableCache();
});

function createGourwestScopedUser(array $permissions, string $viewPermission = 'individual'): User
{
    $role = Role::create([
        'name' => 'Gourwest QA Role '.bin2hex(random_bytes(4)),
        'description' => 'Scoped role for Gourwest Two-Gate authorization verification',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'Gourwest Rep '.bin2hex(random_bytes(3)),
        'email' => 'gw-rep-'.bin2hex(random_bytes(4)).'@luminaracrm.local',
        'password' => bcrypt('StrongGwPass!2026'),
        'status' => 1,
        'view_permission' => $viewPermission,
        'role_id' => $role->id,
    ]);
}

it('enforces 10-locale translation parity and placeholder preservation for gourwest::app.*', function () {
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];

    $enTranslations = Arr::dot(require base_path('packages/Webkul/Gourwest/src/Resources/lang/en/app.php'));
    $expectedKeys = array_keys($enTranslations);

    expect($expectedKeys)->not->toBeEmpty();

    foreach ($locales as $locale) {
        $file = base_path("packages/Webkul/Gourwest/src/Resources/lang/{$locale}/app.php");
        expect(is_file($file))->toBeTrue("Missing Gourwest translation file for locale [{$locale}]");

        $localized = Arr::dot(require $file);

        expect(array_keys($localized))->toEqualCanonicalizing($expectedKeys);

        foreach ($expectedKeys as $key) {
            expect($localized[$key])->toBeString()->not->toBeEmpty();

            preg_match_all('/:[a-z_]+/', (string) $enTranslations[$key], $enMatches);
            foreach ($enMatches[0] as $placeholder) {
                expect($localized[$key])->toContain($placeholder);
            }
        }
    }

    expect(trans('gourwest::app.index.title'))->toBe('Gourwest Global CRM & Integration Hub');
});

it('idempotently seeds the 7-country Gourwest dataset and passes the <3s and 5M warranty benchmark', function () {
    expect(Artisan::call('luminara:seed-gourwest', ['--force' => true]))->toBe(0);

    $firstProfileCount = CustomerProfile::query()->count();
    $firstIntegrationCount = Integration::query()->count();
    $firstTicketCount = ServiceTicket::query()->count();

    // Second run must be 100% idempotent without duplicating records
    expect(Artisan::call('luminara:seed-gourwest', ['--force' => true]))->toBe(0);

    expect(CustomerProfile::query()->count())->toBe($firstProfileCount)->toBeGreaterThanOrEqual(16)
        ->and(Integration::query()->count())->toBe($firstIntegrationCount)->toBe(8)
        ->and(ServiceTicket::query()->count())->toBe($firstTicketCount)->toBeGreaterThanOrEqual(10)
        ->and(SyncEvent::query()->where('status', 'dead_letter')->count())->toBe(2)
        ->and(User::query()->where('email', 'sarah.williams@gourwest.example')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'michael.chen@gourwest.example')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'ethan.carter@gourwest.example')->exists())->toBeTrue()
        ->and(Experiment::query()->where('code', 'gourwest_loyalty_onboarding_v1')->exists())->toBeTrue();

    $seededRegions = CustomerProfile::query()->distinct()->pluck('region_code')->all();
    expect($seededRegions)->toEqualCanonicalizing(['AU', 'NZ', 'SG', 'MY', 'TH', 'UK', 'CA']);

    expect(Artisan::call('luminara:benchmark', ['--json' => true]))->toBe(0);

    $benchmarkJson = json_decode(Artisan::output(), true);

    expect($benchmarkJson)->toBeArray()
        ->and($benchmarkJson['samples'])->toBe(25)
        ->and($benchmarkJson['passes_3s_sla'])->toBeTrue()
        ->and($benchmarkJson['p95_ms'])->toBeLessThanOrEqual(3000.0)
        ->and($benchmarkJson['five_million_ready'])->toBeTrue()
        ->and($benchmarkJson['mfa_ready'])->toBeTrue()
        ->and($benchmarkJson['privacy_regimes_covered'])->toBe(7)
        ->and($benchmarkJson['health_probe_ready'])->toBeTrue();
});

it('generates an idempotent synthetic load dataset, reports the measured volume honestly, and purges it', function () {
    try {
        expect(Artisan::call('luminara:load-dataset', ['--records' => 300]))->toBe(0);
        expect(Artisan::call('luminara:load-dataset', ['--records' => 300]))->toBe(0);

        $loaded = CustomerProfile::query()->where('loyalty_member_number', 'like', 'GWL-%');

        expect((clone $loaded)->count())->toBe(300)
            ->and(Person::query()->where('unique_id', 'like', 'gw-load:%')->count())->toBe(300)
            ->and((clone $loaded)->distinct()->pluck('region_code')->all())
            ->toEqualCanonicalizing(['AU', 'NZ', 'SG', 'MY', 'TH', 'UK', 'CA']);

        expect(Artisan::call('luminara:benchmark', ['--json' => true, '--samples' => 10]))->toBe(0);

        $report = json_decode(Artisan::output(), true);

        // Index readiness alone must never be reported as verified 5M capacity.
        expect($report['samples'])->toBe(10)
            ->and($report['dataset_records'])->toBeGreaterThanOrEqual(300)->toBeLessThan(5000000)
            ->and($report['capacity_verified'])->toBeFalse();
    } finally {
        // ANALYZE TABLE commits implicitly, so the dataset outlives the test transaction.
        Artisan::call('luminara:load-dataset', ['--purge' => true]);
    }

    expect(Person::query()->where('unique_id', 'like', 'gw-load:%')->count())->toBe(0)
        ->and(CustomerProfile::query()->where('loyalty_member_number', 'like', 'GWL-%')->count())->toBe(0)
        ->and(Artisan::call('luminara:load-dataset', ['--records' => 0]))->toBe(1);
});

it('detects cross-region duplicate travellers, losslessly consolidates loyalty points into Golden Records, and records immutable audit logs', function () {
    $admin = getDefaultAdmin();
    Artisan::call('luminara:seed-gourwest', ['--force' => true]);

    $totalPointsBefore = (int) CustomerProfile::query()->where('is_primary_golden', true)->sum('loyalty_points');

    $overviewBefore = test()->actingAs($admin, 'user')
        ->getJson(route('admin.gourwest.overview'))
        ->assertSuccessful()
        ->json('data');

    expect($overviewBefore['kpis']['duplicate_clusters'])->toBe(2);

    $mergeResponse = test()->actingAs($admin, 'user')
        ->postJson(route('admin.gourwest.deduplicate'))
        ->assertSuccessful()
        ->json('data');

    expect($mergeResponse['clusters_merged'])->toBe(2)
        ->and($mergeResponse['profiles_merged'])->toBe(2)
        ->and($mergeResponse['points_consolidated'])->toBe(2770);

    // Total points across all active Golden Records must be conserved with zero point loss or inflation
    $totalPointsAfter = (int) CustomerProfile::query()->where('is_primary_golden', true)->sum('loyalty_points');
    expect($totalPointsAfter)->toBe($totalPointsBefore);

    $auPrimary = CustomerProfile::query()->where('loyalty_member_number', 'GW-AU-1001')->firstOrFail();
    $sgSecondary = CustomerProfile::query()->where('loyalty_member_number', 'GW-SG-1002')->firstOrFail();

    expect($auPrimary->is_primary_golden)->toBeTrue()
        ->and($auPrimary->loyalty_points)->toBe(5300)
        ->and($auPrimary->loyalty_tier)->toBe('Platinum')
        ->and($sgSecondary->is_primary_golden)->toBeFalse()
        ->and($sgSecondary->merged_into_profile_id)->toBe($auPrimary->id)
        ->and($sgSecondary->loyalty_points)->toBe(0);

    $ukPrimary = CustomerProfile::query()->where('loyalty_member_number', 'GW-UK-1003')->firstOrFail();
    $caSecondary = CustomerProfile::query()->where('loyalty_member_number', 'GW-CA-1004')->firstOrFail();

    expect($ukPrimary->is_primary_golden)->toBeTrue()
        ->and($ukPrimary->loyalty_points)->toBe(2270)
        ->and($ukPrimary->loyalty_tier)->toBe('Gold')
        ->and($caSecondary->is_primary_golden)->toBeFalse()
        ->and($caSecondary->merged_into_profile_id)->toBe($ukPrimary->id);

    expect(
        SecurityAuditLog::query()
            ->where('event', 'gourwest.golden_record.merged')
            ->where('auditable_id', (string) $auPrimary->id)
            ->exists()
    )->toBeTrue();
});

it('enforces strict Two-Gate authorization across ACL routes and individual/group data scopes', function () {
    // Unauthenticated requests redirect to login
    test()->get(route('admin.gourwest.index'))
        ->assertRedirect(route('admin.session.create'));

    // Gate 1: Custom role without gourwest ACL receives 401 across all Gourwest routes
    $unprivileged = createGourwestScopedUser(['dashboard'], 'global');

    test()->actingAs($unprivileged, 'user')->get(route('admin.gourwest.index'))->assertStatus(401);
    test()->actingAs($unprivileged, 'user')->getJson(route('admin.gourwest.overview'))->assertStatus(401);
    test()->actingAs($unprivileged, 'user')->postJson(route('admin.gourwest.deduplicate'))->assertStatus(401);
    test()->actingAs($unprivileged, 'user')->postJson(route('admin.gourwest.integrations.replay'))->assertStatus(401);
    test()->actingAs($unprivileged, 'user')->postJson(route('admin.gourwest.benchmark'))->assertStatus(401);

    // Gate 2: Individual-scoped CX specialists only see and mutate their own profiles and SLA tickets
    $cxPermissions = ['dashboard', 'gourwest', 'gourwest.customers', 'gourwest.loyalty', 'gourwest.sla'];
    $repA = createGourwestScopedUser($cxPermissions, 'individual');
    $repB = createGourwestScopedUser($cxPermissions, 'individual');

    $personA = Person::create([
        'name' => 'Scoped Guest Alpha',
        'emails' => [['value' => 'alpha.scoped@gourwest.example', 'label' => 'work']],
        'contact_numbers' => [['value' => '+61499111222', 'label' => 'mobile']],
        'user_id' => $repA->id,
        'unique_id' => 'gw-test:alpha-'.bin2hex(random_bytes(4)),
    ]);

    $personB = Person::create([
        'name' => 'Scoped Guest Bravo',
        'emails' => [['value' => 'bravo.scoped@gourwest.example', 'label' => 'work']],
        'contact_numbers' => [['value' => '+6598887766', 'label' => 'mobile']],
        'user_id' => $repB->id,
        'unique_id' => 'gw-test:bravo-'.bin2hex(random_bytes(4)),
    ]);

    $profileA = CustomerProfile::create([
        'person_id' => $personA->id,
        'owner_user_id' => $repA->id,
        'golden_record_uid' => 'GW-TEST-ALPHA',
        'region_code' => 'AU',
        'home_outlet' => 'Melbourne Test Outlet',
        'privacy_regime' => 'AU-APPs',
        'consent_status' => 'verified',
        'loyalty_member_number' => 'GW-TEST-9901',
        'loyalty_tier' => 'Silver',
        'loyalty_points' => 900,
        'lifetime_spend_aud' => 1100.00,
        'is_primary_golden' => true,
    ]);

    $profileB = CustomerProfile::create([
        'person_id' => $personB->id,
        'owner_user_id' => $repB->id,
        'golden_record_uid' => 'GW-TEST-BRAVO',
        'region_code' => 'SG',
        'home_outlet' => 'Singapore Test Outlet',
        'privacy_regime' => 'SG-PDPA',
        'consent_status' => 'verified',
        'loyalty_member_number' => 'GW-TEST-9902',
        'loyalty_tier' => 'Gold',
        'loyalty_points' => 2500,
        'lifetime_spend_aud' => 3100.00,
        'is_primary_golden' => true,
    ]);

    $ticketA = ServiceTicket::create([
        'ticket_number' => 'GW-SLA-TESTA',
        'person_id' => $personA->id,
        'user_id' => $repA->id,
        'region_code' => 'AU',
        'channel' => 'mobile_app',
        'category' => 'loyalty_discrepancy',
        'priority' => 'high',
        'status' => 'open',
        'subject' => 'Rep A own ticket',
    ]);

    $ticketB = ServiceTicket::create([
        'ticket_number' => 'GW-SLA-TESTB',
        'person_id' => $personB->id,
        'user_id' => $repB->id,
        'region_code' => 'SG',
        'channel' => 'web_portal',
        'category' => 'privacy_request',
        'priority' => 'critical',
        'status' => 'open',
        'subject' => 'Rep B confidential ticket',
    ]);

    // Rep A renders the HTML cockpit cleanly
    test()->actingAs($repA, 'user')
        ->get(route('admin.gourwest.index'))
        ->assertSuccessful()
        ->assertSee('v-gourwest-hub', false);

    // Rep A overview ONLY contains Rep A's customer profile & ticket
    $repAOverview = test()->actingAs($repA, 'user')
        ->getJson(route('admin.gourwest.overview'))
        ->assertSuccessful()
        ->json('data');

    $visibleProfileIds = array_column($repAOverview['customers'], 'id');
    $visibleTicketIds = array_column($repAOverview['tickets'], 'id');

    expect($visibleProfileIds)->toContain($profileA->id)->not->toContain($profileB->id)
        ->and($visibleTicketIds)->toContain($ticketA->id)->not->toContain($ticketB->id);

    // Rep A is denied attempting to resolve Rep B's ticket or adjust Rep B's loyalty points
    test()->actingAs($repA, 'user')
        ->postJson(route('admin.gourwest.tickets.resolve', $ticketB->id), ['resolution_note' => 'Unauthorized'])
        ->assertStatus(401);

    test()->actingAs($repA, 'user')
        ->postJson(route('admin.gourwest.loyalty.adjust'), [
            'profile_id' => $profileB->id,
            'points_delta' => 500,
            'reason' => 'Unauthorized cross-rep adjustment',
        ])
        ->assertStatus(401);

    // Individual-scoped user cannot trigger global demo seeding
    test()->actingAs($repA, 'user')
        ->postJson(route('admin.gourwest.seed'))
        ->assertStatus(401);

    // Rep A CAN adjust their own customer's points and resolve their own ticket
    test()->actingAs($repA, 'user')
        ->postJson(route('admin.gourwest.loyalty.adjust'), [
            'profile_id' => $profileA->id,
            'points_delta' => 1200,
            'reason' => 'Approved ANZ loyalty credit',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.loyalty_points', 2100)
        ->assertJsonPath('data.loyalty_tier', 'Gold');

    test()->actingAs($repA, 'user')
        ->postJson(route('admin.gourwest.tickets.resolve', $ticketA->id), ['resolution_note' => 'Verified with guest'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'resolved');

    // Group-scope check: sharing a regional group grants peer visibility within that group
    $anzGroup = Group::firstOrCreate(['name' => 'ANZ QA Scope Group'], ['description' => 'ANZ shared group']);
    $groupRep = createGourwestScopedUser($cxPermissions, 'group');
    $repA->groups()->syncWithoutDetaching([$anzGroup->id]);
    $groupRep->groups()->syncWithoutDetaching([$anzGroup->id]);

    $groupOverview = test()->actingAs($groupRep, 'user')
        ->getJson(route('admin.gourwest.overview'))
        ->assertSuccessful()
        ->json('data');

    expect(array_column($groupOverview['customers'], 'id'))->toContain($profileA->id)->not->toContain($profileB->id);
});

it('authenticates HMAC-SHA256 POS webhooks, rejects unsigned or forged payloads, and replays dead-letter sync events', function () {
    config(['gourwest.webhook_secret' => 'gw_enterprise_hmac_secret_2026']);

    $admin = getDefaultAdmin();
    Artisan::call('luminara:seed-gourwest', ['--force' => true]);

    $payloadArray = [
        'region_code' => 'AU',
        'event_type' => 'pos.order.completed',
        'external_ref' => 'POS-MEL-778899',
        'loyalty_member_number' => 'GW-AU-1006',
        'points_delta' => 1300,
        'spend_aud' => 185.50,
    ];
    $rawPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

    // 1. Missing X-Gourwest-Signature must be rejected with 401
    test()->call(
        'POST',
        route('api.gourwest.webhooks.ingest', ['systemCode' => 'pos']),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        $rawPayload
    )->assertStatus(401);

    // 2. Forged X-Gourwest-Signature must be rejected with 401
    test()->call(
        'POST',
        route('api.gourwest.webhooks.ingest', ['systemCode' => 'pos']),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_GOURWEST_SIGNATURE' => 'sha256='.str_repeat('a', 64),
        ],
        $rawPayload
    )->assertStatus(401);

    // 3. Valid HMAC-SHA256 signature processes cleanly, records latency, and updates loyalty tier
    $validSig = 'sha256='.hash_hmac('sha256', $rawPayload, 'gw_enterprise_hmac_secret_2026');

    $webhookResponse = test()->call(
        'POST',
        route('api.gourwest.webhooks.ingest', ['systemCode' => 'pos']),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_GOURWEST_SIGNATURE' => $validSig,
        ],
        $rawPayload
    )->assertSuccessful()->json();

    expect($webhookResponse['status'])->toBe('processed')
        ->and($webhookResponse['data']['external_ref'])->toBe('POS-MEL-778899')
        ->and($webhookResponse['data']['latency_ms'])->toBeGreaterThanOrEqual(1)
        ->and($webhookResponse['data']['customer_updated'])->toBeTrue();

    $updatedNoah = CustomerProfile::query()->where('loyalty_member_number', 'GW-AU-1006')->firstOrFail();
    expect($updatedNoah->loyalty_points)->toBe(2140)
        ->and($updatedNoah->loyalty_tier)->toBe('Gold')
        ->and($webhookResponse['data']['duplicate'])->toBeFalse()
        ->and($webhookResponse['data']['points_balance'])->toBe(2140)
        ->and($webhookResponse['data']['tier'])->toBe('Gold');

    // 3b. DEF-01: the same reference delivered again changes nothing and replays the first event
    $eventsBefore = SyncEvent::query()->count();

    $repeatResponse = test()->call(
        'POST',
        route('api.gourwest.webhooks.ingest', ['systemCode' => 'pos']),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_GOURWEST_SIGNATURE' => $validSig,
        ],
        $rawPayload
    )->assertSuccessful()->json();

    expect($repeatResponse['status'])->toBe('processed')
        ->and($repeatResponse['data']['event_id'])->toBe($webhookResponse['data']['event_id'])
        ->and($repeatResponse['data']['duplicate'])->toBeTrue()
        ->and($repeatResponse['data']['customer_updated'])->toBeFalse()
        ->and($repeatResponse['data']['points_balance'])->toBeNull()
        ->and($repeatResponse['data']['tier'])->toBeNull()
        ->and(SyncEvent::query()->count())->toBe($eventsBefore)
        ->and($updatedNoah->fresh()->loyalty_points)->toBe(2140);

    // 4. Replay dead-letter events restores all 8 integrations to healthy with 0 failed events
    expect(SyncEvent::query()->where('status', 'dead_letter')->count())->toBe(2);

    $replayData = test()->actingAs($admin, 'user')
        ->postJson(route('admin.gourwest.integrations.replay'))
        ->assertSuccessful()
        ->json('data');

    expect($replayData['replayed_count'])->toBe(2)
        ->and(SyncEvent::query()->where('status', 'dead_letter')->count())->toBe(0)
        ->and(Integration::query()->where('status', '!=', 'healthy')->count())->toBe(0);
});
