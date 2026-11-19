<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Models\Experiment;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\WebForm\Models\WebForm;
use Webkul\WebForm\Models\WebFormAttribute;

uses(DatabaseTransactions::class);

it('issues HMAC-signed subject_token, blocks unsigned conversion spoofing in production mode, and rejects unexposed conversions', function () {
    config(['experiment.allow_unsigned_subject_header' => false]);

    $experiment = Experiment::create([
        'code' => 'hmac_token_guard_exp',
        'name' => 'HMAC Token Guard',
        'status' => Experiment::STATUS_RUNNING,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
        ],
        'salt' => 'hmac_guard_salt_1',
    ]);

    // Unsigned X-Experiment-Subject conversion spoofing without token/cookie must fail with 422
    test()->withHeader('X-Experiment-Subject', 'spoofed-subject-001')
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'purchase',
            'value' => 500,
        ])
        ->assertStatus(422)
        ->assertJsonPath('recorded', false);

    // Tampered X-Experiment-Token must also fail with 422
    test()->withHeader('X-Experiment-Token', 'eyJzaWQiOiJldmlsIn0.deadbeef')
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'purchase',
        ])
        ->assertStatus(422);

    // Valid token for a visitor who was NEVER exposed to this experiment must NOT synthesize an exposure
    $unexposedToken = app(ExperimentManager::class)->signSubjectToken('never-exposed-visitor');

    test()->withHeader('X-Experiment-Token', $unexposedToken)
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'purchase',
            'value' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('recorded', false);

    expect(DB::table('experiment_events')->where('experiment_id', $experiment->id)->count())->toBe(0);

    // In production mode (allow_unsigned_subject_header = false), passing X-Experiment-Subject to assign MUST NOT mint a token for that arbitrary ID
    $assignResponse = test()->withHeader('X-Experiment-Subject', 'victim-impersonation-attempt')
        ->getJson(route('experiments.public.assign', $experiment->code))
        ->assertOk()
        ->json();

    expect($assignResponse)->toHaveKey('subject_token')
        ->and($assignResponse['subject_token'])->toBeString()->not->toBeEmpty()
        ->and(app(ExperimentManager::class)->verifySubjectToken($assignResponse['subject_token']))->not->toBe('victim-impersonation-attempt');

    test()->withHeader('X-Experiment-Token', $assignResponse['subject_token'])
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'enterprise_contract',
            'value' => 2500000.75,
            'metadata' => ['plan' => 'enterprise'],
        ])
        ->assertOk()
        ->assertJsonPath('recorded', true);

    $stats = app(ExperimentManager::class)->statsFor($experiment);
    expect($stats['total_exposures'])->toBe(1)
        ->and($stats['total_conversions'])->toBe(1)
        ->and($stats['total_value'])->toBe(2500000.75);
});

it('filters bot User-Agents out of exposure telemetry, counts distinct converted subjects, and prevents admin back-office contamination', function () {
    $admin = getDefaultAdmin();
    $pipeline = securityTestPipeline();

    $experiment = Experiment::create([
        'code' => 'clean_attribution_exp',
        'name' => 'Clean Attribution Test',
        'status' => Experiment::STATUS_RUNNING,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
        ],
        'salt' => 'clean_attr_salt',
    ]);

    // Bot User-Agent gets deterministic variant without writing an exposure row
    test()->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'X-Experiment-Subject' => 'googlebot-probe-1',
    ])->getJson(route('experiments.public.assign', $experiment->code))
        ->assertOk();

    expect(DB::table('experiment_events')->where('experiment_id', $experiment->id)->count())->toBe(0);

    // Expose a human visitor and record two distinct conversion event keys -> conversions must stay 1 (unique subject) while summing value
    $manager = app(ExperimentManager::class);
    $manager->variant($experiment->code, 'repeat-buyer-1', true);
    $manager->recordConversion($experiment->code, 'repeat-buyer-1', 'lead:101', 1200.0);
    $manager->recordConversion($experiment->code, 'repeat-buyer-1', 'lead:102', 800.0);

    $stats = $manager->statsFor($experiment);
    expect($stats['total_exposures'])->toBe(1)
        ->and($stats['total_conversions'])->toBe(1)
        ->and($stats['total_value'])->toBe(2000.0);

    // Expose the CRM admin's browser session, then create a Lead inside /admin/leads -> must NOT attribute to experiment!
    $manager->variant($experiment->code, 'admin-browser-sid', true);

    webFormAttributeFor('leads', 'title');
    webFormAttributeFor('persons', 'name');

    test()->actingAs($admin, 'user')
        ->withHeader('X-Experiment-Subject', 'admin-browser-sid')
        ->postJson(route('admin.leads.store'), [
            'entity_type' => 'leads',
            'title' => 'Manual Backoffice Deal',
            'lead_value' => 99000,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $pipeline->stages()->first()->id,
            'lead_source_id' => Source::query()->first()->id,
            'lead_type_id' => Type::query()->first()->id,
            'person' => [
                'name' => 'Backoffice Client',
                'entity_type' => 'persons',
            ],
        ]);

    $afterAdminLeadStats = $manager->statsFor($experiment);
    expect($afterAdminLeadStats['total_conversions'])->toBe(1)
        ->and($afterAdminLeadStats['total_value'])->toBe(2000.0);
});

it('enforces CORS allow-list on /experiments/*, locks mid-run splits, serves winner_variant_key when concluded, and scopes Gate-2 data access', function () {
    config(['cors.allowed_origins' => ['https://landing.luminaradigital.com']]);

    test()->call('OPTIONS', '/experiments/track', [], [], [], [
        'HTTP_ORIGIN' => 'https://landing.luminaradigital.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', 'https://landing.luminaradigital.com');

    test()->call('OPTIONS', '/experiments/track', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.invalid',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])->assertStatus(204)
        ->assertHeader('Vary', 'Origin')
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    $admin = getDefaultAdmin();

    $created = test()->actingAs($admin, 'user')
        ->postJson(route('admin.settings.experiments.store'), [
            'code' => 'winner_lock_test',
            'name' => 'Winner & Lock Test',
            'status' => 'running',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
                ['key' => 'challenger', 'name' => 'Challenger', 'weight' => 5000],
            ],
        ])
        ->assertCreated()
        ->json('data');

    // Mutating weights after experiment has started must fail with 422
    test()->actingAs($admin, 'user')
        ->putJson(route('admin.settings.experiments.update', $created['id']), [
            'code' => 'winner_lock_test',
            'name' => 'Winner & Lock Test',
            'status' => 'running',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Control', 'weight' => 8000],
                ['key' => 'challenger', 'name' => 'Challenger', 'weight' => 2000],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['variants']);

    // Concluding with winner_variant_key = challenger serves challenger to all visitors
    test()->actingAs($admin, 'user')
        ->putJson(route('admin.settings.experiments.update', $created['id']), [
            'code' => 'winner_lock_test',
            'name' => 'Winner & Lock Test',
            'status' => 'concluded',
            'traffic_allocation' => 10000,
            'winner_variant_key' => 'challenger',
            'variants' => [
                ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
                ['key' => 'challenger', 'name' => 'Challenger', 'weight' => 5000],
            ],
        ])
        ->assertOk();

    expect(app(ExperimentManager::class)->variant('winner_lock_test', 'any-visitor-123'))->toBe('challenger');

    // Gate-2 individual scope user cannot read or delete admin's experiment
    $role = Role::create([
        'name' => 'Individual Exp Role '.bin2hex(random_bytes(4)),
        'description' => 'Scoped role',
        'permission_type' => 'custom',
        'permissions' => [
            'settings',
            'settings.other_settings',
            'settings.other_settings.experiments',
            'settings.other_settings.experiments.delete',
        ],
    ]);

    $scopedUser = User::create([
        'name' => 'Scoped Rep',
        'email' => 'scoped-'.bin2hex(random_bytes(4)).'@luminaracrm.local',
        'password' => bcrypt('StrongPass!234'),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => $role->id,
    ]);

    test()->actingAs($scopedUser, 'user')
        ->getJson(route('admin.settings.experiments.stats', $created['id']))
        ->assertStatus(401);

    test()->actingAs($scopedUser, 'user')
        ->deleteJson(route('admin.settings.experiments.delete', $created['id']))
        ->assertStatus(401);

    // Individual-scoped user also cannot view/delete system-seeded (user_id = null) experiments
    $systemExperiment = Experiment::create([
        'code' => 'system_null_owner_exp',
        'name' => 'System Experiment',
        'status' => Experiment::STATUS_DRAFT,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
        ],
        'salt' => 'sys_null_salt',
        'user_id' => null,
    ]);

    test()->actingAs($scopedUser, 'user')
        ->getJson(route('admin.settings.experiments.stats', $systemExperiment->id))
        ->assertStatus(401);
});

it('calibrates Empirical-Bayes lead propensity scores from closed CRM history and attaches expected_value to WebForm conversions', function () {
    $admin = getDefaultAdmin();
    $pipeline = securityTestPipeline();

    $wonStage = $pipeline->stages()->where('code', 'won')->first()
        ?? $pipeline->stages()->create(['name' => 'Won', 'code' => 'won', 'probability' => 100, 'sort_order' => 98]);
    $lostStage = $pipeline->stages()->where('code', 'lost')->first()
        ?? $pipeline->stages()->create(['name' => 'Lost', 'code' => 'lost', 'probability' => 0, 'sort_order' => 99]);

    $highSource = Source::create(['name' => 'Enterprise Partner Referral']);
    $lowSource = Source::create(['name' => 'Cold Purchased List']);
    $leadType = Type::query()->first();

    // Seed historical closed outcomes: Partner Referral wins 8/8, Cold List wins 0/8
    for ($i = 0; $i < 8; $i++) {
        Lead::create([
            'title' => "Historical Won {$i}",
            'lead_value' => 10000,
            'status' => 1,
            'user_id' => $admin->id,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $wonStage->id,
            'lead_source_id' => $highSource->id,
            'lead_type_id' => $leadType->id,
        ]);

        Lead::create([
            'title' => "Historical Lost {$i}",
            'lead_value' => 10000,
            'status' => 1,
            'user_id' => $admin->id,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $lostStage->id,
            'lead_source_id' => $lowSource->id,
            'lead_type_id' => $leadType->id,
        ]);
    }

    $openStage = $pipeline->stages()->whereNotIn('code', ['won', 'lost'])->first();

    $strongLead = Lead::create([
        'title' => 'Partner Inbound Opportunity',
        'lead_value' => 50000,
        'status' => 1,
        'user_id' => $admin->id,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $openStage->id,
        'lead_source_id' => $highSource->id,
        'lead_type_id' => $leadType->id,
    ]);

    $weakLead = Lead::create([
        'title' => 'Cold List Opportunity',
        'lead_value' => 50000,
        'status' => 1,
        'user_id' => $admin->id,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $openStage->id,
        'lead_source_id' => $lowSource->id,
        'lead_type_id' => $leadType->id,
    ]);

    $strongScore = test()->actingAs($admin, 'user')
        ->getJson(route('admin.settings.experiments.propensity', $strongLead->id))
        ->assertOk()
        ->json('data');

    $weakScore = test()->actingAs($admin, 'user')
        ->getJson(route('admin.settings.experiments.propensity', $weakLead->id))
        ->assertOk()
        ->json('data');

    expect($strongScore['win_probability'])->toBeGreaterThan($weakScore['win_probability'])
        ->and($strongScore['expected_value'])->toBeGreaterThan($weakScore['expected_value'])
        ->and($strongScore['drivers'])->toContain('high_converting_source');

    // Verify cross-origin _exp_token WebForm submission records propensity metadata
    $experiment = Experiment::create([
        'code' => 'webform_token_propensity',
        'name' => 'WebForm Token Propensity',
        'status' => Experiment::STATUS_RUNNING,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
        ],
        'salt' => 'wf_token_salt',
    ]);

    config(['experiment.allow_unsigned_subject_header' => false]);

    $assign = test()->getJson(route('experiments.public.assign', $experiment->code))
        ->assertOk()
        ->json();

    $webForm = WebForm::create([
        'form_id' => 'wf_prop_'.bin2hex(random_bytes(5)),
        'title' => 'Cross-Origin Lead Form',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thanks',
        'create_lead' => 1,
    ]);

    foreach ([['persons', 'name'], ['persons', 'emails'], ['leads', 'title'], ['leads', 'lead_value']] as $idx => [$entity, $code]) {
        $attr = webFormAttributeFor($entity, $code);
        if ($code === 'lead_value') {
            $attr->update(['type' => 'price']);
        }
        WebFormAttribute::create([
            'name' => $code,
            'is_required' => 0,
            'is_hidden' => 0,
            'sort_order' => $idx + 1,
            'attribute_id' => $attr->id,
            'web_form_id' => $webForm->id,
        ]);
    }

    // While CRM admin is signed in, previewing/submitting a public WebForm must NOT contaminate visitor conversions
    test()->postJson(route('admin.settings.web_forms.form_store', $webForm->id), [
        '_exp_token' => $assign['subject_token'],
        'persons' => [
            'name' => 'Staff Preview Test',
            'emails' => [['value' => 'staff-'.bin2hex(random_bytes(4)).'@example.invalid', 'label' => 'work']],
        ],
        'leads' => [
            'title' => 'Staff Preview Deal',
            'lead_value' => 99999,
        ],
    ])->assertOk();

    expect(
        DB::table('experiment_events')
            ->where('experiment_id', $experiment->id)
            ->where('event_type', 'conversion')
            ->count()
    )->toBe(0);

    // Anonymous visitor submitting the WebForm with _exp_token records conversion + propensity metadata
    auth()->guard('user')->logout();

    test()->postJson(route('admin.settings.web_forms.form_store', $webForm->id), [
        '_exp_token' => $assign['subject_token'],
        'persons' => [
            'name' => 'Alex CrossOrigin',
            'emails' => [['value' => 'alex-'.bin2hex(random_bytes(4)).'@example.invalid', 'label' => 'work']],
        ],
        'leads' => [
            'title' => 'Cross-Origin Attributed Deal',
            'lead_value' => 20000,
        ],
    ])->assertOk();

    $conv = DB::table('experiment_events')
        ->where('experiment_id', $experiment->id)
        ->where('event_type', 'conversion')
        ->first();

    $meta = json_decode((string) $conv->metadata, true);

    expect($conv)->not->toBeNull()
        ->and($meta)->toHaveKeys(['lead_id', 'win_probability', 'expected_value', 'propensity_grade']);
});
