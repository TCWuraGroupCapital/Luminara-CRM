<?php

namespace Webkul\Agent\Actions\Leads;

class GetLead extends LeadAction
{
    public function name(): string
    {
        return 'leads_get';
    }

    public function summary(): string
    {
        return 'Read one lead, including its description.';
    }

    public function permission(): string
    {
        return 'leads.view';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer'],
        ];
    }

    public function handle(array $params): array
    {
        $lead = $this->scopedLeads()->with(['stage', 'person'])->whereKey($params['lead_id'])->firstOrFail();

        return ['lead' => $this->present($lead) + ['description' => $lead->getAttribute('description')]];
    }
}
