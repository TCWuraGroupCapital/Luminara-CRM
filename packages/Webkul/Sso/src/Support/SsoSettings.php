<?php

namespace Webkul\Sso\Support;

use Webkul\Security\Support\CoreConfigEncryptor;

/**
 * One business's provider settings, read from the database currently in use, so the caller must
 * have switched to the right workspace first.
 */
final class SsoSettings
{
    public const PREFIX = 'general.sso.oidc.';

    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly array $domains,
        public readonly bool $required,
        public readonly bool $trustProviderMfa,
    ) {}

    public static function current(): self
    {
        $value = static fn (string $name): mixed => core()->getConfigData(self::PREFIX.$name);

        $domains = collect(preg_split('/[\s,;]+/', strtolower((string) $value('domains'))) ?: [])
            ->map(static fn (string $domain): string => ltrim(trim($domain), '@'))
            ->filter(static fn (string $domain): bool => $domain !== '' && str_contains($domain, '.'))
            ->unique()
            ->values()
            ->all();

        return new self(
            enabled: (bool) $value('enabled'),
            issuer: rtrim(trim((string) $value('issuer')), '/'),
            clientId: trim((string) $value('client_id')),
            clientSecret: (string) CoreConfigEncryptor::decryptIfNeeded((string) $value('client_secret')),
            domains: $domains,
            required: (bool) $value('required'),
            trustProviderMfa: (bool) $value('trust_mfa'),
        );
    }

    public function usable(): bool
    {
        return $this->enabled && $this->issuer !== '' && $this->clientId !== '' && $this->clientSecret !== '' && $this->domains !== [];
    }

    public function coversEmail(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, $this->domains, true);
    }
}
