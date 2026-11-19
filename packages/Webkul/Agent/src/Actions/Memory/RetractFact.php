<?php

namespace Webkul\Agent\Actions\Memory;

use Illuminate\Validation\Validator;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Support\ActionCallContext;
use Webkul\Agent\Support\CustomerFacts;

/**
 * F3 — retract a fact, or supersede it with a replacement. The old row is kept, so the history of
 * what the CRM believed and when it changed remains auditable.
 */
class RetractFact extends AgentAction
{
    public function __construct(private CustomerFacts $facts) {}

    public function name(): string
    {
        return 'memory_retract_fact';
    }

    public function summary(): string
    {
        return 'Retract an approved customer fact, or replace it with a corrected one. Always waits for a person to sign it in ink.';
    }

    public function permission(): string
    {
        return 'contacts.persons.edit';
    }

    public function approval(): string
    {
        return self::APPROVAL_ALWAYS;
    }

    public function rules(): array
    {
        return [
            'fact_id' => ['required', 'integer', 'min:1'],
            'replacement' => ['nullable', 'string', 'min:3', 'max:280'],
        ];
    }

    public function afterValidate(Validator $validator, array $params): void
    {
        $replacement = $params['replacement'] ?? null;

        if (! is_string($replacement) || trim($replacement) === '') {
            return;
        }

        $reason = $this->facts->sensitiveReason($replacement);

        if ($reason !== null) {
            $validator->errors()->add('replacement', $reason);
        }
    }

    public function subject(array $params): array
    {
        $fact = $this->facts->findScopedFact((int) $params['fact_id']);

        return [[
            'fact_id' => (int) $fact->id,
            'entity_type' => (string) $fact->entity_type,
            'entity_id' => (int) $fact->entity_id,
            'fact' => (string) $fact->fact,
            'status' => (string) $fact->status,
        ]];
    }

    public function handle(array $params): array
    {
        $userId = auth()->guard('user')->id();
        $call = ActionCallContext::call();
        $decidedBy = $call?->decided_by;
        $approvedBy = $decidedBy !== null
            ? (int) $decidedBy
            : (is_numeric($userId) ? (int) $userId : null);

        $fact = $this->facts->retract(
            (int) $params['fact_id'],
            $params['replacement'] ?? null,
            is_numeric($userId) ? (int) $userId : null,
            $approvedBy,
            $call?->id,
        );

        return ['fact' => $this->facts->present($fact)];
    }
}
