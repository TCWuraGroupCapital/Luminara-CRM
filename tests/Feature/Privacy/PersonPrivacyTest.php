<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Ledger\Models\DataBlock;
use Webkul\Ledger\Support\LedgerAppender;
use Webkul\Ledger\Support\LedgerVerifier;
use Webkul\Privacy\Support\PersonEraser;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    DB::table('data_blocks')->delete();
    DB::table('ledger_epochs')->delete();
    DB::table('ledger_heads')->delete();
});

/**
 * A person with a deal, an email with an attachment, a note, a customer fact and ledger history.
 *
 * @return array{person: Person, lead_id: int, email_id: int, activity_id: int, attachment: string}
 */
function privacySubject(User $admin): array
{
    Storage::fake('local');

    $person = Person::create([
        'name' => 'Dana Privacyton',
        'emails' => [['value' => 'dana.privacyton@example.test', 'label' => 'work']],
        'contact_numbers' => [['value' => '+61400111222', 'label' => 'mobile']],
        'user_id' => $admin->id,
    ]);
    Event::dispatch('contacts.person.create.after', $person);

    $lead = agentLead($admin, 'Renewal for Dana Privacyton');
    DB::table('leads')->where('id', $lead->id)->update(['person_id' => $person->id]);
    Event::dispatch('lead.update.after', $lead->fresh());

    $emailId = DB::table('emails')->insertGetId([
        'subject' => 'Your renewal',
        'source' => 'web',
        'user_type' => 'admin',
        'reply' => '<p>Hi Dana, here is the renewal.</p>',
        'folders' => json_encode(['sent']),
        'from' => json_encode(['crm@example.test']),
        'reply_to' => json_encode(['dana.privacyton@example.test']),
        'message_id' => uniqid('privacy-test-').'@example.test',
        'person_id' => $person->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $attachment = 'emails/'.$emailId.'/renewal.pdf';
    Storage::disk('local')->put($attachment, 'pdf bytes');
    DB::table('email_attachments')->insert(['name' => 'renewal.pdf', 'path' => $attachment, 'size' => 9, 'content_type' => 'application/pdf', 'email_id' => $emailId]);

    $activity = Activity::query()->create(['title' => 'Call Dana', 'type' => 'note', 'comment' => 'Prefers mornings', 'user_id' => $admin->id]);
    DB::table('person_activities')->insert(['person_id' => $person->id, 'activity_id' => $activity->id]);
    Event::dispatch('activity.create.after', $activity);

    DB::table('agent_customer_facts')->insert([
        'entity_type' => 'person', 'entity_id' => $person->id, 'category' => 'preference',
        'fact' => 'Dana prefers morning calls', 'visibility' => 'team', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['person' => $person, 'lead_id' => $lead->id, 'email_id' => $emailId, 'activity_id' => $activity->id, 'attachment' => $attachment];
}

function ledgerMentions(string $needle): int
{
    return DB::table('data_blocks')->whereRaw('LOWER(CAST(canonical_payload AS CHAR)) LIKE ?', ['%'.strtolower($needle).'%'])->count();
}

it('exports everything held about a person as a JSON download', function () {
    $admin = getDefaultAdmin();
    $subject = privacySubject($admin);
    $id = $subject['person']->id;

    $response = $this->actingAs($admin, 'user')->get(route('admin.contacts.persons.privacy.export', $id));

    $response->assertOk()->assertHeader('Content-Disposition', "attachment; filename=\"person-{$id}-data.json\"");

    $data = $response->json();

    expect($data['person']['name'])->toBe('Dana Privacyton')
        ->and($data['person']['emails'][0]['value'])->toBe('dana.privacyton@example.test')
        ->and($data['leads'])->toHaveCount(1)
        ->and(collect($data['activities'])->firstWhere('type', 'note')['comment'] ?? null)->toBe('Prefers mornings')
        ->and($data['emails'][0]['attachments'][0]['name'])->toBe('renewal.pdf')
        ->and($data['customer_facts'][0]['fact'])->toBe('Dana prefers morning calls')
        ->and($data['ledger_entries'])->toBeGreaterThan(0);
});

it('erases a person, keeps the deal and leaves a verifiable ledger with no copy of their details', function () {
    $admin = getDefaultAdmin();
    $subject = privacySubject($admin);
    $id = $subject['person']->id;

    expect(ledgerMentions('dana.privacyton@example.test'))->toBeGreaterThan(0);

    $this->actingAs($admin, 'user')
        ->post(route('admin.contacts.persons.privacy.erase', $id), ['confirm_name' => 'Dana'])
        ->assertSessionHasErrors('confirm_name');

    expect(DB::table('persons')->where('id', $id)->value('name'))->toBe('Dana Privacyton');

    $this->actingAs($admin, 'user')
        ->post(route('admin.contacts.persons.privacy.erase', $id), ['confirm_name' => 'Dana Privacyton'])
        ->assertRedirect(route('admin.contacts.persons.view', $id));

    $person = DB::table('persons')->where('id', $id)->first();

    expect($person->name)->toBe(PersonEraser::placeholder($id))
        ->and(json_decode($person->emails, true))->toBe([])
        ->and($person->erased_at)->not->toBeNull()
        ->and(DB::table('emails')->where('id', $subject['email_id'])->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($subject['attachment']))->toBeFalse()
        ->and(DB::table('activities')->where('id', $subject['activity_id'])->exists())->toBeFalse()
        ->and(DB::table('agent_customer_facts')->where('entity_id', $id)->where('entity_type', 'person')->exists())->toBeFalse();

    $lead = DB::table('leads')->where('id', $subject['lead_id'])->first();

    expect($lead)->not->toBeNull()
        ->and((int) $lead->person_id)->toBe($id)
        ->and($lead->title)->toBe('Renewal for '.PersonEraser::placeholder($id));

    expect(ledgerMentions('dana.privacyton@example.test'))->toBe(0)
        ->and(ledgerMentions('privacyton'))->toBe(0)
        ->and(ledgerMentions('+61400111222'))->toBe(0)
        ->and(DataBlock::query()->where('event_name', 'ledger.redaction')->count())->toBe(1)
        ->and(DataBlock::query()->where('event_name', 'person.erased')->count())->toBe(1);

    $verification = app(LedgerVerifier::class)->verifyChain();

    expect($verification['ok'])->toBeTrue($verification['errors'] === [] ? '' : implode("\n", $verification['errors']));

    $redacted = DataBlock::query()->whereNotNull('redacted_at')->first();

    expect($redacted->canonical_payload)->toBe(['redacted' => true])
        ->and(app(LedgerVerifier::class)->verifyReceipt($redacted->receipt_code)['verified'])->toBeTrue();

    $this->actingAs($admin, 'user')
        ->post(route('admin.contacts.persons.privacy.erase', $id), ['confirm_name' => PersonEraser::placeholder($id)])
        ->assertSessionHas('warning');
});

it('rejects a redacted block that no signed redaction record names', function () {
    $admin = getDefaultAdmin();
    $appender = app(LedgerAppender::class);

    $block = $appender->append('person.created', 'person', '991', ['name' => 'Hidden Tamper'], 'human', $admin->id);
    $appender->append('lead.created', 'lead', '992', ['title' => 'After'], 'human', $admin->id);

    DB::table('data_blocks')->where('id', $block->id)->update([
        'canonical_payload' => json_encode(['redacted' => true]),
        'redacted_at' => now()->format('Y-m-d H:i:s.u'),
    ]);

    $verification = app(LedgerVerifier::class)->verifyChain();

    expect($verification['ok'])->toBeFalse()
        ->and(implode(' ', $verification['errors']))->toContain('without a signed redaction record');

    // Forging an attestation by editing an existing block breaks that block's own hash.
    $forged = $appender->append('ledger.redaction', 'person', '991', ['reason' => 'x', 'redacted' => []], 'human', $admin->id);

    DB::table('data_blocks')->where('id', $forged->id)->update([
        'canonical_payload' => json_encode(['reason' => 'x', 'redacted' => [['height' => $block->block_height, 'block_hash' => $block->block_hash, 'payload_sha256' => str_repeat('0', 64)]]]),
    ]);

    $errors = implode(' ', app(LedgerVerifier::class)->verifyChain()['errors']);

    expect($errors)->toContain("Block #{$forged->block_height}")->toContain('hash mismatch');
});

it('allows privacy requests only with the privacy permission', function () {
    $admin = getDefaultAdmin();
    $subject = privacySubject($admin);
    $id = $subject['person']->id;

    $viewer = agentUser(['contacts', 'contacts.persons', 'contacts.persons.view']);

    $export = $this->actingAs($viewer, 'user')->get(route('admin.contacts.persons.privacy.export', $id));
    $erase = $this->actingAs($viewer, 'user')->post(route('admin.contacts.persons.privacy.erase', $id), ['confirm_name' => 'Dana Privacyton']);

    expect(in_array($export->status(), [401, 403], true))->toBeTrue("export answered {$export->status()}")
        ->and(in_array($erase->status(), [401, 403], true))->toBeTrue("erase answered {$erase->status()}")
        ->and(DB::table('persons')->where('id', $id)->value('erased_at'))->toBeNull();

    $page = $this->actingAs($admin, 'user')->get(route('admin.contacts.persons.view', $id));

    $page->assertOk()->assertSee(route('admin.contacts.persons.privacy.export', $id), false);
});

it('erases from the console only with the exact name', function () {
    $admin = getDefaultAdmin();
    $id = privacySubject($admin)['person']->id;

    $this->artisan('luminara:privacy:erase', ['person' => $id, '--confirm' => 'dana'])->assertFailed();
    $this->artisan('luminara:privacy:erase', ['person' => $id, '--confirm' => 'Dana Privacyton'])->assertSuccessful();

    expect(DB::table('persons')->where('id', $id)->value('name'))->toBe(PersonEraser::placeholder($id));
});
