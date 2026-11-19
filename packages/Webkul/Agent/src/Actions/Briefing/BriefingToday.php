<?php

namespace Webkul\Agent\Actions\Briefing;

use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Support\BriefingData;

class BriefingToday extends AgentAction
{
    public function __construct(private BriefingData $briefing) {}

    public function name(): string
    {
        return 'briefing_today';
    }

    public function summary(): string
    {
        return 'Read today\'s morning briefing: waiting approvals, unreviewed agent creates, open and rotting leads, due activities, playbook version, and ledger head.';
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
        return [];
    }

    public function handle(array $params): array
    {
        return $this->briefing->build();
    }
}
