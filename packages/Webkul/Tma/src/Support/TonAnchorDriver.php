<?php

namespace Webkul\Tma\Support;

use Illuminate\Support\Facades\Http;
use Throwable;
use Webkul\Automation\Services\WebhookService;
use Webkul\Ledger\Contracts\AnchorDriver;
use Webkul\Ledger\Models\LedgerEpoch;

final class TonAnchorDriver implements AnchorDriver
{
    public function name(): string
    {
        return 'ton';
    }

    public function commitment(string $merkleRoot): string
    {
        return 'ton:merkle:'.$merkleRoot;
    }

    public function anchor(LedgerEpoch $epoch): ?string
    {
        $merkleRoot = (string) $epoch->getAttribute('merkle_root');
        $commitment = $this->commitment($merkleRoot);
        $relayerUrl = trim((string) config('tma.ton_relayer_url', ''));

        if ($relayerUrl === '') {
            return 'ton:staged:'.substr($merkleRoot, 0, 24);
        }

        if (! $this->isSafeRelayerUrl($relayerUrl)) {
            return null;
        }

        $curlOptions = [];

        if (! app()->runningUnitTests() && defined('CURLOPT_RESOLVE')) {
            $resolvedEntry = $this->buildCurlResolveEntry($relayerUrl);

            if ($resolvedEntry === null) {
                return null;
            }

            $curlOptions[\CURLOPT_RESOLVE] = [$resolvedEntry];
        }

        try {
            $pending = Http::connectTimeout(5)
                ->timeout(10)
                ->withoutRedirecting()
                ->acceptJson();

            if ($curlOptions !== []) {
                $pending = $pending->withOptions(['curl' => $curlOptions]);
            }

            $response = $pending->post($relayerUrl, [
                'epoch_id' => $epoch->getKey(),
                'start_height' => (int) $epoch->getAttribute('start_height'),
                'end_height' => (int) $epoch->getAttribute('end_height'),
                'merkle_root' => $merkleRoot,
                'commitment' => $commitment,
                'server_signature' => (string) ($epoch->getAttribute('server_signature') ?? ''),
                'contract' => (string) config('tma.ton_contract_address', ''),
            ]);

            if (! $response->successful()) {
                return null;
            }

            $txRef = $response->json('tx_hash') ?? $response->json('boc_hash') ?? $response->json('proof');

            if (is_string($txRef) && $txRef !== '') {
                return str_starts_with($txRef, 'ton:') ? $txRef : 'ton:tx:'.$txRef;
            }

            return $commitment;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function isSafeRelayerUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $isTesting = (bool) app()->runningUnitTests();

        if ($host === '' || ($scheme !== 'https' && (! $isTesting || $scheme !== 'http'))) {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! $isTesting) {
            return app(WebhookService::class)->isAllowedEndpoint($url);
        }

        return true;
    }

    private function buildCurlResolveEntry(string $url): ?string
    {
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'http' ? 80 : 443));

        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                ? (str_contains($host, ':') ? "{$host}:{$port}:[{$host}]" : "{$host}:{$port}:{$host}")
                : null;
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $rec) {
                $candidate = $rec['ip'] ?? ($rec['ipv6'] ?? null);

                if (is_string($candidate) && $candidate !== '') {
                    $ips[] = $candidate;
                }
            }
        }

        if ($ips === []) {
            $v4 = @gethostbynamel($host);

            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        $pinned = $ips[0];

        return str_contains($pinned, ':')
            ? "{$host}:{$port}:[{$pinned}]"
            : "{$host}:{$port}:{$pinned}";
    }
}
