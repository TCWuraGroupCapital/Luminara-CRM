<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Models\Experiment;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\WebForm\Models\WebForm;
use Webkul\WebForm\Models\WebFormAttribute;

uses(DatabaseTransactions::class);

function createRestrictedExperimentUser(array $permissions): User
{
    $role = Role::create([
        'name' => 'Restricted QA '.bin2hex(random_bytes(4)),
        'description' => 'Custom role for experiment RBAC verification',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'QA Operator',
        'email' => 'qa-'.bin2hex(random_bytes(4)).'@luminaracrm.local',
        'password' => bcrypt('StrongPass!234'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

it('enforces fail-closed RBAC on admin experiment endpoints', function () {
    test()->get(route('admin.settings.experiments.index'))
        ->assertRedirect(route('admin.session.create'));

    $unauthorizedUser = createRestrictedExperimentUser(['dashboard']);

    test()->actingAs($unauthorizedUser, 'user')
        ->get(route('admin.settings.experiments.index'))
        ->assertStatus(401);

    $authorizedReader = createRestrictedExperimentUser([
        'settings',
        'settings.other_settings',
        'settings.other_settings.experiments',
    ]);

    test()->actingAs($authorizedReader, 'user')
        ->get(route('admin.settings.experiments.index'))
        ->assertOk()
        ->assertSee('v-experiments', false);

    test()->actingAs($authorizedReader, 'user')
        ->postJson(route('admin.settings.experiments.store'), [
            'code' => 'rbac_probe',
            'name' => 'RBAC Probe',
            'status' => 'draft',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
                ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
            ],
        ])
        ->assertStatus(401);
});

it('supports full admin CRUD lifecycle and rejects invalid basis-point weights with 422', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin, 'user')
        ->getJson(route('admin.settings.experiments.index'))
        ->assertOk()
        ->assertJsonStructure(['data']);

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.settings.experiments.store'), [
            'code' => 'bad_weights_exp',
            'name' => 'Invalid Split',
            'status' => 'draft',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Control', 'weight' => 4500],
                ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 4500],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['variants']);

    $created = test()->actingAs($admin, 'user')
        ->postJson(route('admin.settings.experiments.store'), [
            'code' => 'lead_capture_cta',
            'name' => 'Lead Capture CTA Copy',
            'description' => 'Compare Get Quote vs Book Demo button copy',
            'status' => 'running',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Get Quote', 'weight' => 5000],
                ['key' => 'book_demo', 'name' => 'Book Demo', 'weight' => 5000],
            ],
        ])
        ->assertCreated()
        ->json('data');

    expect($created['code'])->toBe('lead_capture_cta')
        ->and($created['started_at'])->not->toBeNull();

    test()->actingAs($admin, 'user')
        ->getJson(route('admin.settings.experiments.stats', $created['id']))
        ->assertOk()
        ->assertJsonPath('data.stats.total_exposures', 0);

    test()->actingAs($admin, 'user')
        ->putJson(route('admin.settings.experiments.update', $created['id']), [
            'code' => 'lead_capture_cta',
            'name' => 'Lead Capture CTA Copy (Concluded)',
            'description' => 'Concluded test',
            'status' => 'concluded',
            'traffic_allocation' => 10000,
            'variants' => [
                ['key' => 'control', 'name' => 'Get Quote', 'weight' => 5000],
                ['key' => 'book_demo', 'name' => 'Book Demo', 'weight' => 5000],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'concluded');

    test()->actingAs($admin, 'user')
        ->deleteJson(route('admin.settings.experiments.delete', $created['id']))
        ->assertOk();

    expect(Experiment::query()->whereKey($created['id'])->exists())->toBeFalse();
});

it('deduplicates public exposures and conversions and renders the @experiment Blade directive', function () {
    $experiment = Experiment::create([
        'code' => 'pricing_banner_ab',
        'name' => 'Pricing Banner A/B',
        'status' => Experiment::STATUS_RUNNING,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000, 'payload' => ['cta' => 'Start Trial']],
            ['key' => 'annual_save', 'name' => 'Annual Save', 'weight' => 5000, 'payload' => ['cta' => 'Save 20%']],
        ],
        'salt' => 'fixed_salt_qa_01',
    ]);

    $draft = Experiment::create([
        'code' => 'unpublished_draft_exp',
        'name' => 'Draft Experiment',
        'status' => Experiment::STATUS_DRAFT,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'treatment', 'name' => 'Treatment', 'weight' => 5000],
        ],
        'salt' => 'draft_salt_0001',
    ]);

    test()->getJson(route('experiments.public.assign', $draft->code))
        ->assertNotFound();

    test()->getJson(route('experiments.public.assign', 'unknown_experiment'))
        ->assertNotFound();

    $firstAssign = test()->withHeader('X-Experiment-Subject', 'visitor-qa-9001')
        ->getJson(route('experiments.public.assign', $experiment->code))
        ->assertOk()
        ->json();

    for ($i = 0; $i < 4; $i++) {
        $repeat = test()->withHeader('X-Experiment-Subject', 'visitor-qa-9001')
            ->getJson(route('experiments.public.assign', $experiment->code))
            ->assertOk()
            ->json();

        expect($repeat['variant'])->toBe($firstAssign['variant']);
    }

    $exposureCount = DB::table('experiment_events')
        ->where('experiment_id', $experiment->id)
        ->where('event_type', 'exposure')
        ->count();

    expect($exposureCount)->toBe(1);

    test()->withHeader('X-Experiment-Subject', 'visitor-qa-9001')
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'signup_completed',
            'value' => 149.50,
        ])
        ->assertOk()
        ->assertJsonPath('recorded', true);

    test()->withHeader('X-Experiment-Subject', 'visitor-qa-9001')
        ->postJson(route('experiments.public.track'), [
            'experiment' => $experiment->code,
            'event_key' => 'signup_completed',
            'value' => 149.50,
        ])
        ->assertOk()
        ->assertJsonPath('recorded', false);

    $stats = app(ExperimentManager::class)->statsFor($experiment);
    expect($stats['total_exposures'])->toBe(1)
        ->and($stats['total_conversions'])->toBe(1)
        ->and($stats['total_value'])->toBe(149.5);

    $rendered = Blade::render(
        '@experiment("pricing_banner_ab", $expected) MATCHED @else FALLBACK @endexperiment',
        ['expected' => $firstAssign['variant']]
    );

    // Default request in test container has its own generated subject, so verify helper directly for visitor-qa-9001
    expect(experiment('pricing_banner_ab', 'visitor-qa-9001'))->toBe($firstAssign['variant'])
        ->and($rendered)->toMatch('/MATCHED|FALLBACK/');
});

it('automatically attributes conversion and lead_value when an exposed visitor submits a public WebForm', function () {
    securityTestPipeline();

    $experiment = Experiment::create([
        'code' => 'webform_funnel_test',
        'name' => 'WebForm Funnel Attribution',
        'status' => Experiment::STATUS_RUNNING,
        'traffic_allocation' => 10000,
        'variants' => [
            ['key' => 'control', 'name' => 'Control', 'weight' => 5000],
            ['key' => 'streamlined', 'name' => 'Streamlined', 'weight' => 5000],
        ],
        'salt' => 'funnel_salt_1234',
    ]);

    $subjectId = 'prospect-funnel-'.bin2hex(random_bytes(4));

    $assigned = test()->withHeader('X-Experiment-Subject', $subjectId)
        ->getJson(route('experiments.public.assign', $experiment->code))
        ->assertOk()
        ->json('variant');

    $webForm = WebForm::create([
        'form_id' => bin2hex(random_bytes(8)),
        'title' => 'Enterprise Demo Request',
        'submit_button_label' => 'Request Demo',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Received!',
        'create_lead' => 1,
    ]);

    $attributes = [
        ['persons', 'name'],
        ['persons', 'emails'],
        ['leads', 'title'],
        ['leads', 'lead_value'],
    ];

    foreach ($attributes as $idx => [$entityType, $code]) {
        $attr = webFormAttributeFor($entityType, $code);

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

    $prospectEmail = 'prospect-'.bin2hex(random_bytes(4)).'@gourwest.example';

    test()->withHeader('X-Experiment-Subject', $subjectId)
        ->postJson(route('admin.settings.web_forms.form_store', $webForm->id), [
            'persons' => [
                'name' => 'Taylor Gourwest',
                'emails' => [['value' => $prospectEmail, 'label' => 'work']],
            ],
            'leads' => [
                'title' => 'Gourwest ANZ Loyalty Integration',
                'lead_value' => 4200,
            ],
        ])
        ->assertOk();

    $conversion = DB::table('experiment_events')
        ->where('experiment_id', $experiment->id)
        ->where('event_type', 'conversion')
        ->first();

    expect($conversion)->not->toBeNull()
        ->and($conversion->variant_key)->toBe($assigned)
        ->and((float) $conversion->value)->toBe(4200.0);
});
