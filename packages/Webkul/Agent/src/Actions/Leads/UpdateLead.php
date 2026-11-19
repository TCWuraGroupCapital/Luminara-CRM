<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Support\Facades\Event;
use Webkul\Lead\Models\Lead;

class UpdateLead extends LeadAction
{
    public function name(): string
    {
        return 'leads_update';
    }

    public function summary(): string
    {
        return 'Update fields on an existing lead (title, description, lead_value, expected_close_date).';
    }

    public function permission(): string
    {
        return 'leads.edit';
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lead_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }

    public function estimatedValue(array $params): ?float
    {
        return isset($params['lead_value']) ? (float) $params['lead_value'] : null;
    }

    public function subject(array $params): array
    {
        return [$this->snapshot($this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail())];
    }

    public function handle(array $params): array
    {
        /** @var Lead $lead */
        $lead = $this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail();

        $fields = [];
        $attributes = [];

        foreach (['title', 'description', 'lead_value', 'expected_close_date'] as $column) {
            if (array_key_exists($column, $params)) {
                $fields[$column] = $params[$column];
                $attributes[] = $column;
            }
        }

        if ($fields !== []) {
            $leadId = $lead->getKey();

            Event::dispatch('lead.update.before', $leadId);

            /** @var Lead $lead */
            $lead = $this->leadRepository->update(
                ['entity_type' => 'leads'] + $fields,
                $leadId,
                $attributes
            );

            Event::dispatch('lead.update.after', $lead);
        }

        return [
            'lead' => $this->present($lead->refresh()) + [
                'description' => $lead->getAttribute('description'),
                'expected_close_date' => $lead->getAttribute('expected_close_date')
                    ? (string) $lead->getAttribute('expected_close_date')
                    : null,
            ],
        ];
    }
}
