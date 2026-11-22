<?php

namespace Webkul\Security\Support;

/**
 * Removes configured secret values from readiness output.
 *
 * Short values are left alone so ordinary words such as a database name are
 * not blanked. Exception text is never copied into the report; this is a
 * second pass in case a check includes a value it should not.
 */
class SecretScrubber
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function scrubReport(array $report): array
    {
        $scrubbed = $this->scrub($report);

        return is_array($scrubbed) ? $scrubbed : $report;
    }

    public function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->scrubString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->sensitiveKey($key)) {
                $out[$key] = '[redacted]';

                continue;
            }

            $out[$key] = $this->scrub($item);
        }

        return $out;
    }

    public function scrubString(string $value): string
    {
        foreach ($this->secrets() as $secret) {
            $value = str_replace($secret, '[redacted]', $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function secrets(): array
    {
        $connection = (string) config('database.default', 'mysql');

        $candidates = [
            config('app.key'),
            config('database.connections.'.$connection.'.password'),
            config('database.redis.default.password'),
            config('database.redis.default.url'),
            config('database.redis.cache.password'),
            config('database.redis.cache.url'),
            config('sentry.dsn'),
            config('security.alert_slack_webhook'),
            config('mail.mailers.smtp.password'),
        ];

        $secrets = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if (strlen($candidate) < 12 || strcasecmp($candidate, 'null') === 0) {
                continue;
            }

            $secrets[] = $candidate;
        }

        return array_values(array_unique($secrets));
    }

    private function sensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|secret|token|api[_-]?key|authorization|dsn|webhook|credential|passwd)/i', $key) === 1;
    }
}
