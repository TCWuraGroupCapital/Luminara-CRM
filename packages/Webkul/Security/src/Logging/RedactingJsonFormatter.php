<?php

namespace Webkul\Security\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * One JSON object per line, with secret context fields replaced.
 *
 * Select it with LOG_CHANNEL=json. The channel writes to stderr so Docker's
 * json-file log driver stores structured lines.
 */
class RedactingJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        return parent::format($record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        ));
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->sensitiveKey($key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function sensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|secret|token|api[_-]?key|authorization|dsn|webhook|credential|passwd)/i', $key) === 1;
    }
}
