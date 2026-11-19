<?php

namespace Webkul\Agent\Actions\Memory;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Validator;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Models\AgentCustomerFact;
use Webkul\Agent\Support\ActionCallContext;
use Webkul\Agent\Support\CustomerFacts;

/**
 * F2 — propose one short fact about a contact. The fact is written only after a person signs the
 * call, with the proposer, the signer and the call all recorded on the row.
 */
class RememberFact extends AgentAction
{
    public function __construct(private CustomerFacts $facts) {}

    public function name(): string
    {
        return 'memory_remember_fact';
    }

    public function summary(): string
    {
        return 'Propose one short fact about a person or organisation. Always waits for a person to sign it in ink before it is saved.';
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
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomerFacts::ENTITY_TYPES)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', 'in:'.implode(',', CustomerFacts::CATEGORIES)],
            'fact' => ['required', 'string', 'min:3', 'max:280'],
            'visibility' => ['nullable', 'string', 'in:'.AgentCustomerFact::VISIBILITY_TEAM.','.AgentCustomerFact::VISIBILITY_PRIVATE],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function afterValidate(Validator $validator, array $params): void
    {
        $fact = trim((string) ($params['fact'] ?? ''));

        if ($fact === '') {
            return;
        }

        $reason = $this->facts->sensitiveReason($fact);

        if ($reason !== null) {
            $validator->errors()->add('fact', $reason);
        }

        $entityType = (string) ($params['entity_type'] ?? '');
        $entityId = (int) ($params['entity_id'] ?? 0);

        /**
         * The duplicate check only runs for a contact the caller can read. For any other contact
         * it is skipped, and `subject()` answers `not_found` whatever the text, so the wording of a
         * stored fact cannot be confirmed from outside the contact's data scope.
         */
        try {
            $this->facts->entity($entityType, $entityId);
        } catch (ModelNotFoundException) {
            return;
        }

        if ($this->facts->hasActiveDuplicate($entityType, $entityId, $fact)) {
            $validator->errors()->add('fact', 'An active fact with the same text already exists for this contact.');
        }
    }

    public function subject(array $params): array
    {
        $entity = $this->facts->entity((string) $params['entity_type'], (int) $params['entity_id']);

        return [[
            'entity_type' => (string) $params['entity_type'],
            'entity_id' => (int) $params['entity_id'],
            'name' => (string) $entity->getAttribute('name'),
            'fact' => trim((string) $params['fact']),
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

        $fact = $this->facts->remember(
            (string) $params['entity_type'],
            (int) $params['entity_id'],
            (string) $params['category'],
            (string) $params['fact'],
            (string) ($params['visibility'] ?? AgentCustomerFact::VISIBILITY_TEAM),
            $params['valid_until'] ?? null,
            is_numeric($userId) ? (int) $userId : null,
            $approvedBy,
            $call?->id,
        );

        return ['fact' => $this->facts->present($fact)];
    }
}
