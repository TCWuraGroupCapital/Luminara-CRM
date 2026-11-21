<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfigurationForm extends FormRequest
{
    /**
     * Determine if the Configuration is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The validation for each field is always resolved from the trusted server-side configuration
     * definition, never from the client supplied `keys[]` payload. This prevents an attacker from
     * relaxing an upload rule (for example to `nullable`) to bypass the allowed file types.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        /**
         * Resolve the rules for the submitted fields from the registered configuration.
         */
        foreach (request()->input('keys', []) as $item) {
            $data = json_decode($item, true);

            foreach ($data['fields'] ?? [] as $field) {
                $key = $data['key'].'.'.$field['name'];

                if ($this->has($key.'.delete')) {
                    continue;
                }

                $rules[$key] = $this->resolveValidation($key);
            }
        }

        /**
         * Every uploaded file must also be validated against its trusted rule, even when the client
         * omits the field from `keys[]`. Files for keys that are not registered configuration fields
         * are rejected outright.
         */
        foreach (array_keys(Arr::dot(request()->allFiles())) as $key) {
            $configField = core()->getConfigField($key);

            $rules[$key] = $configField['validation'] ?? 'prohibited';
        }

        return $rules;
    }

    /**
     * Resolve the validation rule for a configuration field from its registered definition.
     */
    protected function resolveValidation(string $key): string|array
    {
        $configField = core()->getConfigField($key);

        if ($configField === null) {
            return $this->hasFile($key) ? 'prohibited' : 'nullable';
        }

        $validation = $configField['validation'] ?? 'nullable';

        /**
         * A single-choice field only accepts one of its own options. Without this, any string
         * could be stored, and a stored locale that is not a real locale took every page down.
         */
        $allowed = $this->allowedOptionValues($configField);

        if ($allowed === null) {
            return $validation;
        }

        $rules = is_array($validation) ? $validation : explode('|', $validation);

        if (! in_array('nullable', $rules, true) && ! in_array('required', $rules, true)) {
            $rules[] = 'nullable';
        }

        $rules[] = Rule::in($allowed);

        return $rules;
    }

    /**
     * The option values of a select or radio field, or null for any other kind of field.
     *
     * @param  array<string, mixed>  $configField
     * @return array<int, string>|null
     */
    protected function allowedOptionValues(array $configField): ?array
    {
        if (! in_array($configField['type'] ?? null, ['select', 'radio'], true) || empty($configField['options'])) {
            return null;
        }

        $options = $configField['options'];

        if (is_string($options)) {
            [$class, $method] = Str::parseCallback($options);

            $options = app($class)->{$method}();
        }

        return collect($options)
            ->map(static fn ($option) => is_array($option) ? ($option['value'] ?? null) : $option)
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (string) $value)
            ->values()
            ->all();
    }
}
