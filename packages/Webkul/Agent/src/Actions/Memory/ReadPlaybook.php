<?php

namespace Webkul\Agent\Actions\Memory;

use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Support\PlaybookMemory;

class ReadPlaybook extends AgentAction
{
    public function __construct(private PlaybookMemory $playbook) {}

    public function name(): string
    {
        return 'playbook_read';
    }

    public function summary(): string
    {
        return 'Read the workspace business playbook memory blocks (business_identity, pricing_guardrails, communication_voice, active_priorities).';
    }

    public function permission(): string
    {
        return 'leads';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'slot' => ['nullable', 'string', 'in:business_identity,pricing_guardrails,communication_voice,active_priorities'],
        ];
    }

    public function handle(array $params): array
    {
        $blocks = $this->playbook->allBlocks();

        if (! empty($params['slot'])) {
            $slot = (string) $params['slot'];
            $blocks = isset($blocks[$slot]) ? [$slot => $blocks[$slot]] : [];
        }

        return [
            'version' => $this->playbook->version(),
            'blocks' => $blocks,
            'summary' => $this->playbook->promptSummary(),
        ];
    }
}
