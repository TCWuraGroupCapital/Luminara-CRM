<?php

namespace Webkul\Email\Support;

use Webkul\Email\Exceptions\UnsafeSmtpHost;

/**
 * Checks that an outgoing mail server a hosted business typed in is on the public internet.
 */
class SmtpHostGuard
{
    /**
     * Resolve the host and return one public address to connect to.
     *
     * @param  list<int>  $allowedPorts
     *
     * @throws UnsafeSmtpHost
     */
    public function resolvePublicAddress(string $host, int $port, array $allowedPorts): string
    {
        if (! in_array($port, $allowedPorts, true)) {
            throw UnsafeSmtpHost::port($port, $allowedPorts);
        }

        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw UnsafeSmtpHost::host($host);
        }

        $addresses = $this->resolve(trim($host, '[]'));

        if ($addresses === []) {
            throw UnsafeSmtpHost::unresolved($host);
        }

        /**
         * One private answer is enough to refuse: a name that mixes public and private addresses
         * is not one a mail provider would publish.
         */
        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw UnsafeSmtpHost::host($host);
            }
        }

        $ipv4 = array_values(array_filter($addresses, static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false));

        return $ipv4[0] ?? $addresses[0];
    }

    public function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $mapped = $this->unmapIpv4($address);

        if ($mapped !== null) {
            return $this->isPublic($mapped);
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($address);

            /**
             * Ranges PHP's filter does not cover: shared carrier NAT, benchmarking, and the
             * 192.0.0.0/24 protocol block.
             */
            foreach ([['100.64.0.0', 10], ['198.18.0.0', 15], ['192.0.0.0', 24]] as [$base, $bits]) {
                $mask = -1 << (32 - $bits);

                if (($long & $mask) === (ip2long($base) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return false;
        }

        /**
         * IPv6 unique-local (fc00::/7) and link-local (fe80::/10).
         */
        $first = ord($packed[0]);
        $second = ord($packed[1]);

        return ($first & 0xFE) !== 0xFC && ! ($first === 0xFE && ($second & 0xC0) === 0x80);
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }

    private function unmapIpv4(string $address): ?string
    {
        if (! str_contains($address, ':')) {
            return null;
        }

        $packed = inet_pton($address);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return (string) inet_ntop(substr($packed, 12));
        }

        return null;
    }
}
