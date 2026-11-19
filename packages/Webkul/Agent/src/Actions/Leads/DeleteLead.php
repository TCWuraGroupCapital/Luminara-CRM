<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Support\Facades\Event;

class DeleteLead extends LeadAction
{
    public function name(): string
    {
        return 'leads_delete';
    }

    public function summary(): string
    {
        return 'Permanently delete one lead. Always waits for human approval.';
    }

    public function permission(): string
    {
        return 'leads.delete';
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer'],
        ];
    }

    public function approval(): string
    {
        return self::APPROVAL_ALWAYS;
    }

    public function subject(array $params): array
    {
        return [$this->snapshot($this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail())];
    }

    public function handle(array $params): array
    {
        $lead = $this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail();

        Event::dispatch('lead.delete.before', $lead->getKey());

        $this->leadRepository->delete($lead->getKey());

        Event::dispatch('lead.delete.after', $lead->getKey());

        return ['deleted_lead_id' => $lead->getKey()];
    }
}
