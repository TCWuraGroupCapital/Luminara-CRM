<?php

namespace Webkul\Experiment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webkul\Experiment\Models\Experiment;
use Webkul\Experiment\Support\Bucketer;

class ExperimentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $experimentId = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('experiments', 'code')->ignore($experimentId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    Experiment::STATUS_DRAFT,
                    Experiment::STATUS_RUNNING,
                    Experiment::STATUS_PAUSED,
                    Experiment::STATUS_CONCLUDED,
                ]),
            ],
            'traffic_allocation' => ['required', 'integer', 'min:0', 'max:10000'],
            'winner_variant_key' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'variants' => ['required', 'array', 'min:2', 'max:10'],
            'variants.*.key' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'variants.*.name' => ['required', 'string', 'max:80'],
            'variants.*.weight' => ['required', 'integer', 'min:1', 'max:9999'],
            'variants.*.payload' => ['nullable', 'array'],
        ];
    }

    /**
     * Enforce the 10,000 basis-point invariant, winner key validity, and mid-run split immutability.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var array<int, array{key?: mixed, weight?: mixed}> $variants */
                $variants = (array) $this->input('variants', []);
                $sum = array_sum(array_map(fn (array $v): int => (int) ($v['weight'] ?? 0), $variants));

                if ($sum !== Bucketer::TOTAL_BASIS_POINTS) {
                    $validator->errors()->add(
                        'variants',
                        trans('experiment::app.validations.weights-sum')
                    );

                    return;
                }

                $keys = array_map(fn (array $v): string => (string) ($v['key'] ?? ''), $variants);
                $winnerKey = $this->input('winner_variant_key');

                if (is_string($winnerKey) && $winnerKey !== '' && ! in_array($winnerKey, $keys, true)) {
                    $validator->errors()->add(
                        'winner_variant_key',
                        trans('experiment::app.validations.invalid-winner')
                    );
                }

                $experimentId = $this->route('id');

                if ($experimentId !== null) {
                    /** @var Experiment|null $existing */
                    $existing = Experiment::query()->find($experimentId);

                    if ($existing && ($existing->started_at !== null || $existing->events()->exists())) {
                        if ($existing->code !== (string) $this->input('code')) {
                            $validator->errors()->add(
                                'code',
                                trans('experiment::app.validations.locked-after-start')
                            );
                        }

                        $existingSplit = array_map(
                            fn (array $v): array => ['key' => (string) $v['key'], 'weight' => (int) $v['weight']],
                            $existing->variants
                        );
                        $incomingSplit = array_map(
                            fn (array $v): array => ['key' => (string) ($v['key'] ?? ''), 'weight' => (int) ($v['weight'] ?? 0)],
                            array_values($variants)
                        );

                        if ($existingSplit !== $incomingSplit) {
                            $validator->errors()->add(
                                'variants',
                                trans('experiment::app.validations.locked-after-start')
                            );
                        }
                    }
                }
            },
        ];
    }
}
