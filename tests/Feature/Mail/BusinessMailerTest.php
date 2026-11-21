<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Webkul\Admin\Notifications\Common;
use Webkul\Core\Models\CoreConfig;
use Webkul\Email\Exceptions\BusinessMailNotConfigured;
use Webkul\Email\Exceptions\UnsafeSmtpHost;
use Webkul\Email\Mails\Email;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Email\Support\BusinessInbox;
use Webkul\Email\Support\BusinessMailer;
use Webkul\Email\Support\SmtpHostGuard;

/**
 * Each business sends its own email (to leads, contacts, quotes, campaigns) through its own
 * account. A hosted business without one must not fall back to the operator's mailbox, and the
 * server it names must be on the public internet.
 */
uses(DatabaseTransactions::class);

/**
 * A BusinessMailer that can pretend to be a hosted business and records what it would send.
 */
class RecordingBusinessMailer extends BusinessMailer
{
    public bool $hosted = false;

    public ?ArrayTransport $transport = null;

    public bool $realBuild = false;

    public function isHostedBusiness(): bool
    {
        return $this->hosted;
    }

    public function build(array $settings): Mailer
    {
        if ($this->realBuild) {
            return parent::build($settings);
        }

        $this->transport = new ArrayTransport;

        $mailer = new Mailer('business', app('view'), $this->transport, app('events'));
        $mailer->alwaysFrom($settings['from_address'], $settings['from_name']);

        return $mailer;
    }
}

function businessMailer(bool $hosted = false): RecordingBusinessMailer
{
    $mailer = new RecordingBusinessMailer(new SmtpHostGuard);
    $mailer->hosted = $hosted;

    app()->instance(BusinessMailer::class, $mailer);

    return $mailer;
}

function saveSmtpSettings(array $values): void
{
    foreach ($values as $field => $value) {
        CoreConfig::query()->updateOrCreate(['code' => BusinessMailer::CONFIG_PREFIX.$field], ['value' => $value]);
    }
}

function serverMailerMessages(): array
{
    return iterator_to_array(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
}

beforeEach(function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'operator@platform.test', 'mail.from.name' => 'Platform']);
    app('mail.manager')->purge('array');
});

it('refuses mail servers on private networks and unusual ports', function (string $host, int $port) {
    expect(fn () => (new SmtpHostGuard)->resolvePublicAddress($host, $port, BusinessMailer::HOSTED_PORTS))
        ->toThrow(UnsafeSmtpHost::class);
})->with([
    'loopback' => ['127.0.0.1', 587],
    'private 10/8' => ['10.0.0.5', 587],
    'docker bridge' => ['172.17.0.2', 587],
    'home network' => ['192.168.1.1', 587],
    'cloud metadata' => ['169.254.169.254', 587],
    'carrier NAT' => ['100.64.1.1', 587],
    'IPv6 loopback' => ['::1', 587],
    'IPv6 unique local' => ['fd00::1', 587],
    'IPv4-mapped private' => ['::ffff:10.0.0.1', 587],
    'localhost' => ['localhost', 587],
    'internal name' => ['redis.internal', 587],
    'database port' => ['8.8.8.8', 3306],
]);

it('accepts a public mail server address', function () {
    expect((new SmtpHostGuard)->resolvePublicAddress('8.8.8.8', 587, BusinessMailer::HOSTED_PORTS))->toBe('8.8.8.8');
});

it('keeps using the server mailer on a single-business install without its own account', function () {
    businessMailer();

    Mail::send(new Common(['to' => 'lead@example.invalid', 'subject' => 'Hello', 'body' => 'Body']));

    $messages = serverMailerMessages();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getOriginalMessage()->getFrom()[0]->getAddress())->toBe('operator@platform.test');
});

it('sends through the business account, from the business address, once it is saved', function () {
    $business = businessMailer(hosted: true);

    saveSmtpSettings(['host' => 'smtp.example.com', 'port' => '587', 'username' => 'sales', 'from_address' => 'sales@business.test', 'from_name' => 'Business Sales']);

    Mail::send(new Common(['to' => 'lead@example.invalid', 'subject' => 'Hello', 'body' => 'Body']));

    expect(serverMailerMessages())->toBeEmpty()
        ->and($business->transport->messages())->toHaveCount(1);

    $from = $business->transport->messages()->first()->getOriginalMessage()->getFrom()[0];

    expect($from->getAddress())->toBe('sales@business.test')
        ->and($from->getName())->toBe('Business Sales');
});

it('stores and sends a lead email from the business address with replies going back to it', function () {
    $business = businessMailer(hosted: true);

    saveSmtpSettings(['host' => 'smtp.example.com', 'from_address' => 'sales@business.test', 'from_name' => 'Business Sales']);

    $email = app(EmailRepository::class)->create([
        'reply_to' => ['lead@example.invalid'],
        'reply' => '<p>Hello</p>',
        'subject' => 'Proposal',
        'name' => 'Proposal',
    ]);

    expect($email->from)->toBe('sales@business.test');

    Mail::send(new Email($email));

    $message = $business->transport->messages()->first()->getOriginalMessage();

    expect($message->getFrom()[0]->getAddress())->toBe('sales@business.test')
        ->and($message->getReplyTo())->toBeEmpty();
});

it('refuses to send for a hosted business that has not connected an account', function () {
    businessMailer(hosted: true);

    expect(fn () => Mail::send(new Common(['to' => 'lead@example.invalid', 'subject' => 'Hello', 'body' => 'Body'])))
        ->toThrow(BusinessMailNotConfigured::class);

    expect(serverMailerMessages())->toBeEmpty();
});

it('tells the person why a composed email was not sent and keeps it in the outbox', function () {
    businessMailer(hosted: true);

    $response = test()->actingAs(getDefaultAdmin(), 'user')->withHeader('X-Requested-With', 'XMLHttpRequest')->postJson(route('admin.mail.store'), [
        'reply_to' => ['lead@example.invalid'],
        'reply' => 'Body',
        'subject' => 'Hello',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', trans('admin::app.configuration.index.email.smtp.not-configured'));

    expect($response->json('data.folders'))->toBe(['outbox'])
        ->and(serverMailerMessages())->toBeEmpty();
});

it('will not connect a hosted business to a private mail server', function () {
    $business = businessMailer(hosted: true);
    $business->realBuild = true;

    saveSmtpSettings(['host' => '10.0.0.5', 'port' => '587', 'from_address' => 'sales@business.test']);

    expect(fn () => $business->mailer())->toThrow(UnsafeSmtpHost::class);
});

it('asks for saved settings before sending a test email', function () {
    businessMailer(hosted: true);

    test()->actingAs(getDefaultAdmin(), 'user')
        ->postJson(route('admin.configuration.email.smtp_test'))
        ->assertStatus(422)
        ->assertJsonPath('message', trans('admin::app.configuration.index.email.smtp.not-saved'));
});

it('sends the test email to the signed-in person through the business account', function () {
    $business = businessMailer(hosted: true);

    saveSmtpSettings(['host' => 'smtp.example.com', 'from_address' => 'sales@business.test']);

    $admin = getDefaultAdmin();

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.configuration.email.smtp_test'))
        ->assertOk();

    $message = $business->transport->messages()->first()->getOriginalMessage();

    expect($message->getTo()[0]->getAddress())->toBe($admin->email)
        ->and($message->getFrom()[0]->getAddress())->toBe('sales@business.test');
});

it('reads a mailbox only when the business saved one of its own', function () {
    config(['mail-receiver.default' => 'webklex-imap', 'imap.accounts.default.username' => 'operator@platform.test']);

    businessMailer(hosted: true);

    $inbox = app(BusinessInbox::class);

    expect($inbox->shouldPoll())->toBeFalse();

    foreach (['host' => '8.8.8.8', 'port' => '993', 'username' => 'sales@business.test', 'password' => 'secret'] as $field => $value) {
        CoreConfig::query()->updateOrCreate(['code' => BusinessInbox::CONFIG_PREFIX.$field], ['value' => $value]);
    }

    $config = $inbox->clientConfig((array) config('imap.accounts.default'));

    expect($inbox->shouldPoll())->toBeTrue()
        ->and($config['username'])->toBe('sales@business.test')
        ->and($config['host'])->toBe('8.8.8.8')
        ->and($config['validate_cert'])->toBeTrue();
});

it('does not print the server mailbox login into the settings screen', function () {
    config(['imap.accounts.default.username' => 'operator@platform.test', 'imap.accounts.default.password' => 'operator-secret']);

    expect(core()->getConfigData('email.imap.account.username'))->toBeEmpty()
        ->and(core()->getConfigData('email.imap.account.password'))->toBeEmpty();
});

it('keeps the server mailbox for a single-business install that uses IMAP', function () {
    config(['mail-receiver.default' => 'webklex-imap']);

    businessMailer();

    expect(app(BusinessInbox::class)->shouldPoll())->toBeTrue();

    config(['mail-receiver.default' => 'sendgrid']);

    expect(app(BusinessInbox::class)->shouldPoll())->toBeFalse();
});
