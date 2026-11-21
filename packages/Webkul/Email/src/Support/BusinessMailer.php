<?php

namespace Webkul\Email\Support;

use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Webkul\Email\Exceptions\BusinessMailNotConfigured;
use Webkul\Workspace\WorkspaceManager;

/**
 * Picks the account that sends a business's own email: messages to leads and contacts, quotes,
 * automations and campaigns. System email (sign-up, password resets) never comes through here.
 *
 * A business that has filled in Settings > Configuration > Email > Outgoing mail sends through that
 * SMTP account, from its own address. Otherwise:
 *  - a single-business install, and the main business of a multi-business install, keep using the
 *    server's mailer (MAIL_* in .env), as before;
 *  - a hosted business refuses to send, so it can never borrow the operator's mailbox.
 *
 * Settings are read from the current database on every call, so a long-running queue worker that
 * moves between businesses always uses the right account.
 */
class BusinessMailer
{
    public const CONFIG_PREFIX = 'email.smtp.account.';

    /**
     * Ports a hosted business may use: SMTP relay, implicit TLS, submission and the common alternative.
     */
    public const HOSTED_PORTS = [25, 465, 587, 2525];

    public function __construct(protected SmtpHostGuard $guard) {}

    /**
     * @return array{host: string, port: int, username: string, password: string, from_address: string, from_name: string}
     */
    public function settings(): array
    {
        $value = static fn (string $field): string => trim((string) core()->getConfigData(self::CONFIG_PREFIX.$field));

        return [
            'host' => strtolower($value('host')),
            'port' => (int) ($value('port') ?: 587),
            'username' => $value('username'),
            'password' => (string) core()->getConfigData(self::CONFIG_PREFIX.'password'),
            'from_address' => $value('from_address'),
            'from_name' => $value('from_name'),
        ];
    }

    /**
     * Whether this business has its own outgoing account filled in.
     */
    public function configured(): bool
    {
        $settings = $this->settings();

        return $settings['host'] !== '' && filter_var($settings['from_address'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * A hosted business (not the main one) must bring its own account.
     */
    public function isHostedBusiness(): bool
    {
        if (! class_exists(WorkspaceManager::class) || ! app()->bound(WorkspaceManager::class)) {
            return false;
        }

        $manager = app(WorkspaceManager::class);

        return $manager->enabled() && $manager->current() !== null;
    }

    /**
     * The address a business's email is sent from, for storing on the email record.
     */
    public function fromAddress(): string
    {
        return $this->configured() ? $this->settings()['from_address'] : (string) config('mail.from.address');
    }

    public function fromName(): string
    {
        if (! $this->configured()) {
            return (string) config('mail.from.name');
        }

        return $this->settings()['from_name'] ?: (string) config('app.name');
    }

    /**
     * The mailer to send this business's email with, or null to use the server's default mailer.
     *
     * @throws BusinessMailNotConfigured when a hosted business has no account of its own
     */
    public function mailer(): ?MailerContract
    {
        if (! $this->configured()) {
            if ($this->isHostedBusiness()) {
                throw BusinessMailNotConfigured::make();
            }

            return null;
        }

        return $this->build($this->settings());
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string, from_address: string, from_name: string}  $settings
     */
    public function build(array $settings): Mailer
    {
        $host = $settings['host'];
        $port = $settings['port'];
        $connectTo = $host;

        /**
         * A hosted business could otherwise aim this at the server's own network (the database,
         * Redis) and read the replies through the test button. Resolve once, check the address,
         * then connect to that exact address so the name cannot be re-pointed in between.
         */
        if ($this->isHostedBusiness()) {
            $connectTo = $this->guard->resolvePublicAddress($host, $port, self::HOSTED_PORTS);
        }

        $transport = new EsmtpTransport(
            str_contains($connectTo, ':') ? '['.$connectTo.']' : $connectTo,
            $port,
            $port === 465 ? true : null,
        );

        if ($settings['username'] !== '') {
            $transport->setUsername($settings['username']);
            $transport->setPassword($settings['password']);
        }

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout(15);

            if ($connectTo !== $host) {
                /**
                 * Connected by address, so tell TLS which name the certificate must match.
                 */
                $stream->setStreamOptions(array_replace_recursive($stream->getStreamOptions(), [
                    'ssl' => ['peer_name' => $host],
                ]));
            }
        }

        $mailer = new Mailer('business', app('view'), $transport, app('events'));

        if (app()->bound('queue')) {
            $mailer->setQueue(app('queue'));
        }

        $mailer->alwaysFrom($settings['from_address'], $settings['from_name'] ?: (string) config('app.name'));

        return $mailer;
    }
}
