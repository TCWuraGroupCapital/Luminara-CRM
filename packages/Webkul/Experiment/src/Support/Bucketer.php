<?php

namespace Webkul\Experiment\Support;

use InvalidArgumentException;

final class Bucketer
{
    public const TOTAL_BASIS_POINTS = 10000;

    /**
     * Largest multiple of 10,000 below 2^32 (429496 * 10000) for zero-modulo-bias rejection sampling.
     */
    private const MAX_UNBIASED_UINT32 = 4294960000;

    /**
     * Deterministically map an experiment domain + subject into [0, 9999] with zero delimiter collisions or modulo bias.
     */
    public static function bucket(string $domain, string $experimentCode, string $salt, string $subjectId): int
    {
        $message = $domain."\0".strlen($experimentCode).':'.$experimentCode."\0".$subjectId;
        $digest = hash_hmac('sha256', $message, $salt !== '' ? $salt : 'luminara-default-salt', true);

        /** @var array<int, int> $words */
        $words = unpack('N8', $digest);

        foreach ($words as $word) {
            if ($word < self::MAX_UNBIASED_UINT32) {
                return $word % self::TOTAL_BASIS_POINTS;
            }
        }

        return $words[8] % self::TOTAL_BASIS_POINTS;
    }

    /**
     * Check whether a subject falls inside the monotonic traffic allocation [0..10000].
     */
    public static function isAllocated(string $experimentCode, string $salt, string $subjectId, int $trafficAllocation): bool
    {
        if ($trafficAllocation <= 0) {
            return false;
        }

        if ($trafficAllocation >= self::TOTAL_BASIS_POINTS) {
            return true;
        }

        return self::bucket('alloc', $experimentCode, $salt, $subjectId) < $trafficAllocation;
    }

    /**
     * Assign a subject to a variant key given basis-point weights summing to 10000.
     *
     * @param  array<int, array{key: string, weight: int|string}>  $variants
     */
    public static function assignVariant(
        string $experimentCode,
        string $salt,
        string $subjectId,
        array $variants,
        int $trafficAllocation = self::TOTAL_BASIS_POINTS
    ): ?string {
        self::assertValidVariants($variants);

        if (! self::isAllocated($experimentCode, $salt, $subjectId, $trafficAllocation)) {
            return null;
        }

        $slot = self::bucket('variant', $experimentCode, $salt, $subjectId);
        $cumulative = 0;

        foreach ($variants as $variant) {
            $cumulative += (int) $variant['weight'];

            if ($slot < $cumulative) {
                return (string) $variant['key'];
            }
        }

        return (string) $variants[array_key_last($variants)]['key'];
    }

    /**
     * Enforce boundary invariants on variant definitions.
     *
     * @param  array<int, array{key?: mixed, weight?: mixed}>  $variants
     */
    public static function assertValidVariants(array $variants): void
    {
        if (count($variants) < 2) {
            throw new InvalidArgumentException('An experiment requires at least 2 variants.');
        }

        $totalWeight = 0;
        $seenKeys = [];

        foreach ($variants as $variant) {
            $key = isset($variant['key']) && is_string($variant['key']) ? $variant['key'] : '';
            $parsedWeight = isset($variant['weight']) && (is_int($variant['weight']) || is_string($variant['weight']))
                ? filter_var($variant['weight'], FILTER_VALIDATE_INT)
                : false;
            $weight = $parsedWeight !== false ? (int) $parsedWeight : -1;

            if ($key === '' || ! preg_match('/^[a-z0-9_]{1,32}$/', $key)) {
                throw new InvalidArgumentException("Invalid variant key [{$key}].");
            }

            if (isset($seenKeys[$key])) {
                throw new InvalidArgumentException("Duplicate variant key [{$key}].");
            }

            if ($weight <= 0) {
                throw new InvalidArgumentException("Variant [{$key}] must have a positive basis-point weight.");
            }

            $seenKeys[$key] = true;
            $totalWeight += $weight;
        }

        if ($totalWeight !== self::TOTAL_BASIS_POINTS) {
            throw new InvalidArgumentException(
                sprintf('Variant weights must sum to %d basis points, got %d.', self::TOTAL_BASIS_POINTS, $totalWeight)
            );
        }
    }
}
