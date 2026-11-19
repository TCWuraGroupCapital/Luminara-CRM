<?php

namespace Webkul\Agent\Support;

/**
 * Derives a JSON Schema from an action's validation rules, so the schema an agent reads can never
 * disagree with the validation the server applies.
 */
final class JsonSchema
{
    private const TYPES = [
        'string' => 'string',
        'email' => 'string',
        'date' => 'string',
        'integer' => 'integer',
        'numeric' => 'number',
        'boolean' => 'boolean',
        'array' => 'array',
    ];

    /**
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, mixed>
     */
    public static function fromRules(array $rules): array
    {
        $properties = [];
        $required = [];
        $nested = [];

        foreach ($rules as $field => $fieldRules) {
            if (preg_match('/^([^\.]+)\.\*\.([^\.]+)$/', $field, $matches)) {
                $nested[$matches[1]][$matches[2]] = $fieldRules;

                continue;
            }

            if (str_ends_with($field, '.*')) {
                $properties[substr($field, 0, -2)]['items'] = self::property($fieldRules);

                continue;
            }

            $properties[$field] = self::property($fieldRules) + ($properties[$field] ?? []);

            if (in_array('required', $fieldRules, true)) {
                $required[] = $field;
            }
        }

        foreach ($nested as $arrayField => $subRules) {
            $properties[$arrayField]['items'] = self::fromRules($subRules);
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties ?: new \stdClass,
            'required' => $required,
            'additionalProperties' => false,
        ], fn ($value) => $value !== []);
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<string, mixed>
     */
    private static function property(array $rules): array
    {
        $type = 'string';
        $schema = [];

        foreach ($rules as $rule) {
            if (isset(self::TYPES[$rule])) {
                $type = self::TYPES[$rule];
            }

            if ($rule === 'email' || $rule === 'date') {
                $schema['format'] = $rule;
            }
        }

        [$minimum, $maximum] = match ($type) {
            'string' => ['minLength', 'maxLength'],
            'array' => ['minItems', 'maxItems'],
            default => ['minimum', 'maximum'],
        };

        foreach ($rules as $rule) {
            [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

            match ($name) {
                'min' => $schema[$minimum] = $argument + 0,
                'max' => $schema[$maximum] = $argument + 0,
                'in' => $schema['enum'] = explode(',', (string) $argument),
                default => null,
            };
        }

        return ['type' => in_array('nullable', $rules, true) ? [$type, 'null'] : $type] + $schema;
    }
}
