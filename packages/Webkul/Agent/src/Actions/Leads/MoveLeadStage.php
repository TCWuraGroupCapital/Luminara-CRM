<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\StageRepository;

class MoveLeadStage extends LeadAction
{
    public function __construct(LeadRepository $leadRepository, protected StageRepository $stageRepository)
    {
        parent::__construct($leadRepository);
    }

    public function name(): string
    {
        return 'leads_move_stage';
    }

    public function summary(): string
    {
        return 'Move leads to a pipeline stage. Moving more than one lead in a call waits for human approval.';
    }

    public function permission(): string
    {
        return 'leads.edit';
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['required', 'integer'],
            'stage_id' => ['required', 'integer', 'exists:lead_pipeline_stages,id'],
        ];
    }

    public function approval(): string
    {
        return self::APPROVAL_CONDITIONAL;
    }

    public function estimatedValue(array $params): ?float
    {
        $ids = array_values(array_unique($params['lead_ids'] ?? []));

        if ($ids === []) {
            return 0.0;
        }

        return (float) $this->scopedLeads()->whereIn('id', $ids)->sum('lead_value');
    }

    public function requiresApproval(array $params): bool
    {
        return count(array_unique($params['lead_ids'])) > 1;
    }

    public function subject(array $params): array
    {
        /** @var Stage $stage */
        $stage = $this->stageRepository->findOrFail($params['stage_id']);
        $stageName = $stage->getAttribute('name');

        return $this->leads($params)
            ->map(fn (Lead $lead): array => $this->snapshot($lead) + ['moves_to' => $stageName])
            ->all();
    }

    /**
     * All or nothing: silently skipping a lead the caller cannot see would report a mass update as
     * done when part of it was not.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, Lead>
     */
    private function leads(array $params): Collection
    {
        $ids = array_values(array_unique($params['lead_ids']));

        $leads = $this->scopedLeads()->whereIn('id', $ids)->orderBy('id')->get();

        if ($leads->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel($this->leadRepository->model(), $ids);
        }

        return $leads;
    }

    public function handle(array $params): array
    {
        $leads = $this->leads($params);
        $ids = $leads->modelKeys();

        /** @var Stage $stage */
        $stage = $this->stageRepository->findOrFail($params['stage_id']);
        $stageId = $stage->getKey();
        $pipelineId = $stage->getAttribute('lead_pipeline_id');

        foreach ($leads as $lead) {
            $leadId = $lead->getKey();

            Event::dispatch('lead.update.before', $leadId);

            $updated = $this->leadRepository->update([
                'entity_type' => 'leads',
                'lead_pipeline_id' => $pipelineId,
                'lead_pipeline_stage_id' => $stageId,
            ], $leadId, ['lead_pipeline_id', 'lead_pipeline_stage_id']);

            Event::dispatch('lead.update.after', $updated);
        }

        return ['moved_lead_ids' => $ids, 'stage_id' => $stageId, 'stage' => $stage->getAttribute('name')];
    }
}
