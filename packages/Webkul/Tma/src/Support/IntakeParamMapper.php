<?php

namespace Webkul\Tma\Support;

use Webkul\Agent\Support\ActionRegistry;

final class IntakeParamMapper
{
    /**
     * Map captured text and optional metadata strictly onto parameters declared by `intake_capture`
     * so `ActionExecutor`'s unknown-parameter guard is always satisfied.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function build(ActionRegistry $registry, string $text, string $source, array $extra = []): array
    {
        $action = $registry->find('intake_capture');
        $rules = $action !== null
            ? $action->inputRules()
            : [
                'text' => ['required', 'string'],
                'reason' => ['nullable', 'string'],
            ];

        $params = array_intersect_key($extra, $rules);

        foreach (['text', 'raw_text', 'note', 'content', 'message', 'input'] as $textField) {
            if (array_key_exists($textField, $rules)) {
                $params[$textField] = $text;

                break;
            }
        }

        foreach (['source_channel', 'source', 'channel'] as $sourceField) {
            if (array_key_exists($sourceField, $rules) && ! isset($params[$sourceField])) {
                $params[$sourceField] = $source;

                break;
            }
        }

        if (array_key_exists('reason', $rules) && ! isset($params['reason'])) {
            $params['reason'] = "Captured via {$source}";
        }

        return $params;
    }
}
