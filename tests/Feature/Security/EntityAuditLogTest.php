<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Quote\Models\Quote;
use Webkul\Security\Http\Middleware\AuditDataExport;
use Webkul\Security\Models\SecurityAuditLog;
use Webkul\Security\Support\AuditContext;
use Webkul\Security\Support\SecurityAuditRecorder;

uses(DatabaseTransactions::class);

function auditSecret(): string
{
    return 'ZebraValue'.bin2hex(random_bytes(4));
}

function auditRequestId(string $prefix): string
{
    $id = $prefix.'-'.bin2hex(random_bytes(4));
    AuditContext::set($id);

    return $id;
}

function assertAuditOmits(string $requestId, string $secret): void
{
    $rows = DB::table('security_audit_logs')->where('request_id', $requestId)->get();

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect(json_encode($row, JSON_UNESCAPED_UNICODE))->not->toContain($secret);
    }
}

it('records person changes with the actor, request id, and field names only', function () {
    $admin = getDefaultAdmin();
    $secret = auditSecret();
    $requestId = auditRequestId('req-person');

    test()->actingAs($admin, 'user');

    $person = Person::create([
        'name' => $secret,
        'emails' => [['value' => $secret.'@audit.test', 'label' => 'work']],
        'user_id' => $admin->id,
    ]);

    $created = SecurityAuditLog::query()
        ->where('request_id', $requestId)
        ->where('event', 'person.created')
        ->where('auditable_id', (string) $person->id)
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->actor_id)->toBe($admin->id)
        ->and($created->actor_email)->toBe($admin->email)
        ->and($created->auditable_type)->toBe('person')
        ->and($created->workspace)->toBeNull()
        ->and($created->metadata['action'])->toBe('created')
        ->and($created->metadata['fields'])->toContain('name')
        ->and($created->metadata['fields'])->toContain('emails');

    $updatesBefore = SecurityAuditLog::query()
        ->where('auditable_id', (string) $person->id)
        ->where('event', 'person.updated')
        ->count();

    $person->touch();

    expect(SecurityAuditLog::query()->where('auditable_id', (string) $person->id)->where('event', 'person.updated')->count())
        ->toBe($updatesBefore);

    $person->update(['job_title' => 'Buyer '.$secret]);

    $updated = SecurityAuditLog::query()
        ->where('request_id', $requestId)
        ->where('event', 'person.updated')
        ->where('auditable_id', (string) $person->id)
        ->latest('id')
        ->first();

    expect($updated)->not->toBeNull()
        ->and($updated->metadata['fields'])->toContain('job_title')
        ->and($updated->metadata['action'])->toBe('updated');

    $personId = (string) $person->id;
    $person->delete();

    $deleted = SecurityAuditLog::query()
        ->where('event', 'person.deleted')
        ->where('auditable_id', $personId)
        ->first();

    expect($deleted)->not->toBeNull()
        ->and($deleted->metadata['fields'])->toBe([])
        ->and($deleted->metadata['action'])->toBe('deleted');

    assertAuditOmits($requestId, $secret);

    test()->actingAs($admin, 'user')
        ->get(route('admin.security.audit_logs.index', [
            'entity' => 'person',
            'action' => 'created',
            'request_id' => $requestId,
        ]))
        ->assertOk()
        ->assertSee('person.created')
        ->assertSee($requestId)
        ->assertSee(trans('security::app.audit.filter-entity'))
        ->assertDontSee('person.deleted')
        ->assertDontSee($secret);

    test()->actingAs($admin, 'user')
        ->getJson(route('admin.security.audit_logs.index', [
            'entity' => 'organization',
            'request_id' => $requestId,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('records organization, lead, quote, and activity field names without their values', function () {
    $admin = getDefaultAdmin();
    $secret = auditSecret();
    $requestId = auditRequestId('req-entities');

    test()->actingAs($admin, 'user');

    $organization = Organization::create(['name' => 'Org '.$secret, 'user_id' => $admin->id]);
    $organization->update(['name' => 'Renamed '.$secret]);

    $lead = agentLead($admin, 'Lead '.$secret);
    $lead->update(['lead_value' => 424242]);

    $quote = Quote::create([
        'subject' => 'Subject '.$secret,
        'description' => 'Opening note',
        'user_id' => $admin->id,
        'person_id' => Person::create([
            'name' => 'Quote contact',
            'user_id' => $admin->id,
            'emails' => [['value' => 'quote-'.bin2hex(random_bytes(3)).'@audit.test', 'label' => 'work']],
        ])->id,
        'sub_total' => 10,
        'grand_total' => 10,
    ]);
    $quote->update(['description' => 'Body '.$secret]);

    $activity = Activity::create([
        'title' => 'Call '.$secret,
        'type' => 'note',
        'comment' => $secret,
        'is_done' => 0,
        'user_id' => $admin->id,
    ]);
    $activity->update(['is_done' => 1]);

    $updated = SecurityAuditLog::query()
        ->where('request_id', $requestId)
        ->where('event', 'activity.updated')
        ->where('auditable_id', (string) $activity->id)
        ->first();

    expect(SecurityAuditLog::query()->where('request_id', $requestId)->where('event', 'organization.created')->where('auditable_id', (string) $organization->id)->exists())->toBeTrue()
        ->and(SecurityAuditLog::query()->where('request_id', $requestId)->where('event', 'organization.updated')->where('auditable_id', (string) $organization->id)->first()?->metadata['fields'])->toContain('name')
        ->and(SecurityAuditLog::query()->where('request_id', $requestId)->where('event', 'lead.updated')->where('auditable_id', (string) $lead->id)->first()?->metadata['fields'])->toContain('lead_value')
        ->and(SecurityAuditLog::query()->where('request_id', $requestId)->where('event', 'quote.updated')->where('auditable_id', (string) $quote->id)->first()?->metadata['fields'])->toContain('description')
        ->and($updated)->not->toBeNull()
        ->and($updated->metadata['fields'])->toContain('is_done');

    assertAuditOmits($requestId, $secret);
    assertAuditOmits($requestId, '424242');
});

it('records a custom attribute code and not the value that was saved', function () {
    $admin = getDefaultAdmin();
    $secret = auditSecret();
    $requestId = auditRequestId('req-attr');

    test()->actingAs($admin, 'user');

    $person = Person::create([
        'name' => 'Attribute host',
        'user_id' => $admin->id,
        'emails' => [['value' => 'host-'.bin2hex(random_bytes(3)).'@audit.test', 'label' => 'work']],
    ]);

    $attribute = Attribute::create([
        'code' => 'fav_colour',
        'name' => 'Favourite colour',
        'type' => 'text',
        'entity_type' => 'persons',
        'is_user_defined' => 1,
    ]);

    AttributeValue::create([
        'attribute_id' => $attribute->id,
        'entity_type' => 'persons',
        'entity_id' => $person->id,
        'text_value' => $secret,
    ]);

    $log = SecurityAuditLog::query()
        ->where('request_id', $requestId)
        ->where('event', 'person.updated')
        ->where('auditable_id', (string) $person->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['fields'])->toContain('fav_colour')
        ->and($log->metadata['fields'])->not->toContain('text_value');

    assertAuditOmits($requestId, $secret);
});

it('records one lead deletion and batches a mass delete into a single insert', function () {
    $admin = getDefaultAdmin();
    $secret = auditSecret();

    test()->actingAs($admin, 'user');

    $lead = agentLead($admin, 'Delete '.$secret);

    test()->actingAs($admin, 'user')
        ->deleteJson(route('admin.leads.delete', $lead->id))
        ->assertSuccessful();

    expect(SecurityAuditLog::query()->where('event', 'lead.deleted')->where('auditable_id', (string) $lead->id)->count())->toBe(1);
    assertAuditOmits(
        (string) SecurityAuditLog::query()->where('event', 'lead.deleted')->where('auditable_id', (string) $lead->id)->value('request_id'),
        $secret
    );

    SecurityAuditRecorder::deferWrites(true);

    try {
        $ids = [];

        foreach (range(1, 5) as $index) {
            $ids[] = agentLead($admin, 'Mass audit '.$index)->id;
        }

        SecurityAuditRecorder::flush();

        $inserts = 0;
        DB::listen(function ($query) use (&$inserts) {
            if (preg_match('/insert into .*security_audit_logs/i', $query->sql) === 1) {
                $inserts++;
            }
        });

        test()->actingAs($admin, 'user')
            ->postJson(route('admin.leads.mass_delete'), ['indices' => $ids])
            ->assertSuccessful();

        expect(SecurityAuditLog::query()->where('event', 'lead.deleted')->whereIn('auditable_id', array_map('strval', $ids))->count())->toBe(5)
            ->and($inserts)->toBe(1)
            ->and($inserts)->toBeLessThan(5);
    } finally {
        SecurityAuditRecorder::deferWrites(false);
    }
});

it('records a datagrid csv export without copying the exported values', function () {
    $admin = getDefaultAdmin();
    $secret = auditSecret();

    test()->actingAs($admin, 'user');

    Person::create([
        'name' => $secret,
        'user_id' => $admin->id,
        'emails' => [['value' => $secret.'@audit.test', 'label' => 'work']],
    ]);

    $requestId = 'req-export-'.bin2hex(random_bytes(3));

    $response = test()->actingAs($admin, 'user')
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Request-Id' => $requestId,
        ])
        ->get(route('admin.contacts.persons.index', ['export' => 1, 'format' => 'csv']));

    $response->assertOk();
    $response->assertHeader('X-Request-Id', $requestId);

    $download = $response->baseResponse;
    $csv = $download instanceof BinaryFileResponse
        ? (string) file_get_contents((string) $download->getFile()->getPathname())
        : $response->getContent();

    expect($csv)->toContain($secret);

    $log = SecurityAuditLog::query()->where('event', 'data.exported')->where('request_id', $requestId)->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_type)->toBe('datagrid')
        ->and($log->auditable_id)->toBe('person_data_grid')
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->metadata['action'])->toBe('exported')
        ->and($log->metadata['format'])->toBe('csv')
        ->and($log->metadata['fields'])->toBe([]);

    assertAuditOmits($requestId, $secret);
});

it('records quote, dashboard, and google contact exports and skips failed downloads', function () {
    $middleware = new AuditDataExport;
    $secret = auditSecret();

    $quote = auditNamedRequest('admin.quotes.print', '/admin/quotes/print/15', ['id' => '15']);
    $middleware->handle($quote, fn () => response('Quote PDF for '.$secret, 200));

    $logged = SecurityAuditLog::query()->where('event', 'quote.exported')->where('auditable_id', '15')->first();

    expect($logged)->not->toBeNull()
        ->and($logged->metadata['format'])->toBe('pdf')
        ->and($logged->metadata['action'])->toBe('exported')
        ->and(json_encode($logged->getAttributes()))->not->toContain($secret);

    $before = SecurityAuditLog::query()->where('event', 'quote.exported')->count();
    $middleware->handle($quote, fn () => response('denied', 403));
    expect(SecurityAuditLog::query()->where('event', 'quote.exported')->count())->toBe($before);

    $dashboard = auditNamedRequest('admin.dashboard.export_pdf', '/admin/dashboard/export-pdf');
    $middleware->handle($dashboard, fn () => response('pdf', 200));

    expect(SecurityAuditLog::query()->where('event', 'dashboard.exported')->where('auditable_type', 'dashboard')->exists())->toBeTrue();

    $google = auditNamedRequest('admin.contacts.persons.google_export.store', '/admin/contacts/persons/google-export');
    $middleware->handle($google, fn () => response()->json([
        'message' => 'Started for '.$secret,
        'batch_id' => 9,
    ]));

    $export = SecurityAuditLog::query()->where('event', 'person.exported')->latest('id')->first();

    expect($export)->not->toBeNull()
        ->and($export->auditable_type)->toBe('person')
        ->and($export->metadata['format'])->toBe('google_contacts')
        ->and(json_encode($export->getAttributes()))->not->toContain($secret);
});

/**
 * @param  array<string, string>  $parameters
 */
function auditNamedRequest(string $name, string $uri, array $parameters = []): Request
{
    $request = Request::create($uri, 'GET');
    $route = new Route(['GET'], $uri, fn () => null);
    $route->name($name);
    $route->bind($request);

    foreach ($parameters as $key => $value) {
        $route->setParameter($key, $value);
    }

    $request->setRouteResolver(static fn () => $route);

    return $request;
}
