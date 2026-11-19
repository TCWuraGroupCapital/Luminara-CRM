<?php

namespace Webkul\Agent\Actions\Memory;

use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Support\CustomerFacts;

/**
 * F1 — read the approved facts already known about one contact. Read-only and never held for
 * approval, so an agent can recall context in the same turn in which it needs it.
 */
class RecallFacts extends AgentAction
{
    public function __construct(private CustomerFacts $facts) {}

    public function name(): string
    {
        return 'memory_recall';
    }

    public function summary(): string
    {
        return 'Read the active, human-approved facts known about one person or organisation, newest first. Optionally search their text.';
    }

    public function permission(): string
    {
        return 'contacts.persons.view';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomerFacts::ENTITY_TYPES)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function handle(array $params): array
    {
        $facts = $this->facts
            ->recall((string) $params['entity_type'], (int) $params['entity_id'], $params['query'] ?? null)
            ->map(fn ($fact): array => $this->facts->present($fact))
            ->all();

        return ['facts' => $facts, 'count' => count($facts)];
    }
}
