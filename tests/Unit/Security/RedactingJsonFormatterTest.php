<?php

use Monolog\Level;
use Monolog\LogRecord;
use Webkul\Security\Logging\RedactingJsonFormatter;

it('writes one json object per line and redacts secret fields', function () {
    $formatter = new RedactingJsonFormatter;
    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-11-22T00:00:00Z'),
        channel: 'testing',
        level: Level::Critical,
        message: 'backup failed',
        context: [
            'password' => 'super-secret-db-password-value',
            'nested' => ['api_key' => 'super-secret-api-key-value', 'rows' => 3],
            'count' => 2,
        ],
        extra: ['webhook' => 'https://hooks.example.invalid/secret'],
    );

    $line = $formatter->format($record);
    $decoded = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded['message'])->toBe('backup failed')
        ->and($decoded['context']['password'])->toBe('[redacted]')
        ->and($decoded['context']['nested']['api_key'])->toBe('[redacted]')
        ->and($decoded['context']['nested']['rows'])->toBe(3)
        ->and($decoded['context']['count'])->toBe(2)
        ->and($decoded['extra']['webhook'])->toBe('[redacted]')
        ->and($line)->not->toContain('super-secret-db-password-value')
        ->and($line)->not->toContain('super-secret-api-key-value')
        ->and(str_ends_with(rtrim($line, "\r\n"), '}'))->toBeTrue();
});
