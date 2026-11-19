<?php

namespace Webkul\Agent\Actions\Memory;

use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Models\AgentPreferenceSignal;
use Webkul\Agent\Support\PlaybookMemory;

class ProposePlaybookRule extends AgentAction
{
    public function __construct(private PlaybookMemory $playbook) {}

    public function name(): string
    {
        return 'playbook_propose_rule';
    }

    public function summary(): string
    {
        return 'Propose a standing business rule for one playbook slot. Always waits for a human to sign in ink.';
    }

    public function permission(): string
    {
        return 'agent_briefing';
    }

    public function approval(): string
    {
        return self::APPROVAL_ALWAYS;
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', 'string', 'in:business_identity,pricing_guardrails,communication_voice,active_priorities'],
            'rule' => ['required', 'string', 'min:3', 'max:280'],
        ];
    }

    public function subject(array $params): array
    {
        $slot = (string) $params['slot'];
        $current = $this->playbook->allBlocks()[$slot] ?? ['slot' => $slot, 'version' => 0];

        return [[
            'slot' => $slot,
            'version' => $current['version'],
        ]];
    }

    public function handle(array $params): array
    {
        $slot = (string) $params['slot'];
        $rule = trim((string) $params['rule']);
        $userId = is_numeric(auth()->guard('user')->id()) ? (int) auth()->guard('user')->id() : null;

        $block = $this->playbook->appendRuleToSlot($slot, $rule, $userId);

        AgentPreferenceSignal::query()->create([
            'call_id' => null,
            'user_id' => $userId,
            'action' => $this->name(),
            'signal_type' => 'explicit_rule',
            'param_diff' => ['slot' => $slot],
            'feedback_note' => null,
            'distilled_rule' => $rule,
        ]);

        return [
            'slot' => $block->slot,
            'version' => $block->version,
            'content' => $block->content,
        ];
    }
}
