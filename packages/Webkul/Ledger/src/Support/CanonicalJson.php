<?php

namespace Webkul\Ledger\Support;

use ArrayObject;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use UnitEnum;

class CanonicalJson
{
    private const SECRET_KEY_PATTERN = '/(password|secret|token|api_key|private_key|authorization|recovery_code)/i';

    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * Recursively redact sensitive credential and secret fields before ledger persistence.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function scrubSecrets(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                $scrubbed[$key] = '[REDACTED]';

                continue;
            }

            $scrubbed[$key] = self::scrubValue($value);
        }

        return $scrubbed;
    }

    /**
     * Encode any value as deterministic RFC 8785 JCS JSON.
     */
    public static function encode(mixed $value): string
    {
        $normalized = self::normalize($value);

        return json_encode($normalized, self::ENCODE_FLAGS | JSON_THROW_ON_ERROR);
    }

    private static function scrubValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::scrubSecrets($value);
        }

        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);

            return $vars === [] ? (object) [] : (object) self::scrubSecrets($vars);
        }

        if ($value instanceof ArrayObject) {
            $copy = $value->getArrayCopy();

            return $copy === [] ? (object) [] : (object) self::scrubSecrets($copy);
        }

        return $value;
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (is_nan($value) || is_infinite($value)) {
                throw new InvalidArgumentException('Non-finite floats cannot be encoded in canonical JSON.');
            }

            return $value === 0.0 ? 0.0 : $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value instanceof JsonSerializable
                ? self::normalize($value->jsonSerialize())
                : $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof ArrayObject) {
            $copy = $value->getArrayCopy();

            return $copy === [] ? (object) [] : self::normalizeAssociativeMap($copy);
        }

        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);

            return $vars === [] ? (object) [] : self::normalizeAssociativeMap($vars);
        }

        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize());
        }

        if ($value instanceof Arrayable) {
            return self::normalize($value->toArray());
        }

        if (is_array($value)) {
            if ($value === []) {
                return [];
            }

            if (array_is_list($value)) {
                return array_map([self::class, 'normalize'], $value);
            }

            return self::normalizeAssociativeMap($value);
        }

        if (is_object($value)) {
            $vars = get_object_vars($value);

            return $vars === [] ? (object) [] : self::normalizeAssociativeMap($vars);
        }

        return (string) $value;
    }

    /**
     * @param  array<array-key, mixed>  $map
     */
    private static function normalizeAssociativeMap(array $map): stdClass
    {
        $sorted = [];

        foreach ($map as $key => $val) {
            $sorted[(string) $key] = self::normalize($val);
        }

        ksort($sorted, SORT_STRING);

        return (object) $sorted;
    }
}
