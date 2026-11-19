<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

abstract class LeadAction extends AgentAction
{
    public function __construct(protected LeadRepository $leadRepository) {}

    /**
     * Leads inside the calling user's data scope. A lead outside it is reported as not found, so an
     * agent cannot learn that it exists.
     *
     * @return Builder<Lead>
     */
    protected function scopedLeads(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->leadRepository->getModel()->newQuery());
    }

    /**
     * A lead as an approver should see it. `updated_at` makes any later edit count as a change.
     *
     * @return array<string, scalar|null>
     */
    protected function snapshot(Lead $lead): array
    {
        return $this->present($lead) + ['updated_at' => $lead->getAttribute('updated_at')?->toIso8601String()];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function present(Lead $lead): array
    {
        return [
            'id' => $lead->getKey(),
            'title' => $lead->getAttribute('title'),
            'lead_value' => ($value = $lead->getAttribute('lead_value')) === null ? null : (float) $value,
            'stage_id' => $lead->getAttribute('lead_pipeline_stage_id'),
            'stage' => $lead->getRelationValue('stage')?->getAttribute('name'),
            'person_id' => $lead->getAttribute('person_id'),
            'person' => $lead->getRelationValue('person')?->getAttribute('name'),
            'owner_id' => $lead->getAttribute('user_id'),
            'created_at' => $lead->getAttribute('created_at')?->toIso8601String(),
        ];
    }
}
