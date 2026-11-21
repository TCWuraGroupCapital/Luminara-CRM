<?php

namespace Webkul\Email\Support;

use Webkul\Core\Repositories\CoreConfigRepository;

/**
 * The mailbox a business reads replies from (Settings > Configuration > Email > IMAP).
 *
 * The settings screen pre-fills these fields from the server's IMAP_* values, so a hosted business
 * must only ever use what it saved itself, never those defaults.
 */
class BusinessInbox
{
    public const CONFIG_PREFIX = 'email.imap.account.';

    public const HOSTED_PORTS = [143, 993];

    public function __construct(
        protected CoreConfigRepository $coreConfigRepository,
        protected BusinessMailer $mailer,
        protected SmtpHostGuard $guard,
    ) {}

    /**
     * Whether this business saved a complete mailbox of its own.
     */
    public function hasOwnAccount(): bool
    {
        return $this->saved('host') !== '' && $this->saved('username') !== '' && $this->saved('password') !== '';
    }

    /**
     * Whether `inbound-emails:process` should read a mailbox in the current database.
     */
    public function shouldPoll(): bool
    {
        if ($this->hasOwnAccount()) {
            return true;
        }

        return ! $this->mailer->isHostedBusiness() && config('mail-receiver.default') === 'webklex-imap';
    }

    /**
     * Connection settings for the IMAP client.
     *
     * @param  array<string, mixed>  $defaults  the server's `imap.accounts.default`
     * @return array<string, mixed>
     */
    public function clientConfig(array $defaults): array
    {
        if (! $this->mailer->isHostedBusiness()) {
            return array_merge($defaults, array_filter([
                'host' => core()->getConfigData(self::CONFIG_PREFIX.'host'),
                'port' => core()->getConfigData(self::CONFIG_PREFIX.'port'),
                'encryption' => core()->getConfigData(self::CONFIG_PREFIX.'encryption'),
                'username' => core()->getConfigData(self::CONFIG_PREFIX.'username'),
                'password' => core()->getConfigData(self::CONFIG_PREFIX.'password'),
            ]), [
                'validate_cert' => (bool) core()->getConfigData(self::CONFIG_PREFIX.'validate_cert'),
            ]);
        }

        $host = $this->saved('host');
        $port = (int) ($this->saved('port') ?: 993);

        $this->guard->resolvePublicAddress($host, $port, self::HOSTED_PORTS);

        return array_merge($defaults, [
            'host' => $host,
            'port' => $port,
            'encryption' => $this->saved('encryption') ?: ($port === 993 ? 'ssl' : 'tls'),
            'validate_cert' => true,
            'username' => $this->saved('username'),
            'password' => $this->saved('password'),
        ]);
    }

    /**
     * A value this database saved itself, ignoring the settings screen's defaults.
     */
    protected function saved(string $field): string
    {
        $row = $this->coreConfigRepository->findOneWhere(['code' => self::CONFIG_PREFIX.$field]);

        return is_string($row?->value) ? trim($row->value) : '';
    }
}
