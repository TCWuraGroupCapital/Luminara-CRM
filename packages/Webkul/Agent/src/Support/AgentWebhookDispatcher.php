<?php

namespace Webkul\Agent\Support;

use Illuminate\Support\Facades\Http;
use Throwable;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Automation\Services\WebhookService;

/**
 * Dispatches HMAC-SHA256 signed decision notifications to an Agent Passport's webhook URL.
 */
final class AgentWebhookDispatcher
{
    public function dispatchDecision(AgentActionCall $call): bool
    {
        $passport = $call->getRelationValue('passport');

        if (! $passport instanceof AgentPassport || ! $passport->webhook_url) {
            return false;
        }

        $url = trim($passport->webhook_url);

        if (! $this->isSafeUrl($url)) {
            return false;
        }

        $curlOptions = [];

        if (! app()->runningUnitTests() && defined('CURLOPT_RESOLVE')) {
            $resolvedEntry = $this->buildCurlResolveEntry($url);

            if ($resolvedEntry === null) {
                return false;
            }

            $curlOptions[\CURLOPT_RESOLVE] = [$resolvedEntry];
        }

        $payload = [
            'event' => 'agent.call.decided',
            'call_id' => $call->id,
            'action' => $call->action,
            'status' => $call->status,
            'passport' => $passport->slug,
            'receipt_code' => $call->receipt_code,
            'block_hash' => $call->block_hash,
            'human_feedback' => $call->human_feedback,
            'decided_at' => $call->decided_at?->toIso8601String(),
            'result' => $call->result,
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $secret = (string) ($passport->webhook_secret ?? '');
        $signature = 'sha256='.hash_hmac('sha256', $json, $secret);

        try {
            $pending = Http::connectTimeout(3)
                ->timeout(5)
                ->withoutRedirecting()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Luminara-Event' => 'agent.call.decided',
                    'X-Luminara-Signature' => $signature,
                    'X-Luminara-Delivery' => (string) $call->id,
                ])
                ->withBody($json, 'application/json');

            if ($curlOptions !== []) {
                $pending = $pending->withOptions(['curl' => $curlOptions]);
            }

            $response = $pending->post($url);

            return $response->successful();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function isSafeUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $isTesting = (bool) app()->runningUnitTests();

        if ($host === '' || ($scheme !== 'https' && (! $isTesting || $scheme !== 'http'))) {
            return false;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.internal')
        ) {
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
