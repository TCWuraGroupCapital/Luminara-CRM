<?php

namespace Webkul\Experiment\Listeners;

use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Support\LeadPropensityScorer;
use Webkul\Lead\Contracts\Lead;

class ConversionAttributionListener
{
    public function __construct(
        protected ExperimentManager $experimentManager,
        protected LeadPropensityScorer $propensityScorer
    ) {}

    /**
     * Attribute public inbound CRM leads (e.g. WebForm submissions) to active experiment exposures without admin contamination.
     */
    public function handleLeadCreated(mixed $lead): void
    {
        if (! $lead instanceof Lead) {
            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // Signed-in CRM staff (back-office forms, CSV imports, or live WebForm previews) must never pollute visitor experiments.
        if (auth()->guard('user')->check()) {
            return;
        }

        $subjectId = $this->experimentManager->resolveConversionSubjectId(request());

        if ($subjectId === null || $subjectId === '') {
            return;
        }

        $leadId = isset($lead->id) ? (int) $lead->id : 0;
        $leadValue = isset($lead->lead_value) ? (float) $lead->lead_value : 0.0;

        $this->experimentManager->attributeSubjectConversions(
            subjectId: $subjectId,
            eventKey: $leadId > 0 ? "lead:{$leadId}" : 'lead_created',
            value: $leadValue,
            metadata: $leadId > 0 ? function () use ($lead, $leadId): array {
                $propensity = $this->propensityScorer->score($lead);

                return [
                    'lead_id' => $leadId,
                    'win_probability' => $propensity['win_probability'],
                    'expected_value' => $propensity['expected_value'],
                    'propensity_grade' => $propensity['grade'],
                ];
            } : null,
        );
    }
}
