<?php

namespace Webkul\Experiment\Support;

use Illuminate\Support\Facades\DB;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Models\Lead as LeadModel;

final class LeadPropensityScorer
{
    private const PRIOR_PSEUDO_COUNT = 10.0;

    /**
     * Compute an Empirical-Bayes calibrated win probability, expected deal value, and explainable drivers for a CRM Lead.
     *
     * @return array{
     *     lead_id: int,
     *     win_probability: float,
     *     expected_value: float,
     *     grade: string,
     *     drivers: array<int, string>
     * }
     */
    public function score(Lead $lead): array
    {
        /** @var LeadModel $lead */
        $stageCode = (string) ($lead->stage->code ?? '');
        $leadValue = (float) ($lead->lead_value ?? 0.0);

        if ($stageCode === 'won') {
            return $this->formatResult((int) $lead->id, 0.99, $leadValue, ['closed_won_stage']);
        }

        if ($stageCode === 'lost') {
            return $this->formatResult((int) $lead->id, 0.01, $leadValue, ['closed_lost_stage']);
        }

        $stageProbability = max(0.05, min(0.95, (float) (($lead->stage->probability ?? 50) / 100.0)));
        $sourceId = ! empty($lead->lead_source_id) ? (int) $lead->lead_source_id : 0;
        $typeId = ! empty($lead->lead_type_id) ? (int) $lead->lead_type_id : 0;

        $stats = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereIn('lead_pipeline_stages.code', ['won', 'lost'])
            ->selectRaw(
                "COUNT(*) as total_closed,
                COALESCE(SUM(CASE WHEN lead_pipeline_stages.code = 'won' THEN 1 ELSE 0 END), 0) as total_won,
                COALESCE(SUM(CASE WHEN leads.lead_source_id = ? THEN 1 ELSE 0 END), 0) as source_n,
                COALESCE(SUM(CASE WHEN leads.lead_source_id = ? AND lead_pipeline_stages.code = 'won' THEN 1 ELSE 0 END), 0) as source_won,
                COALESCE(SUM(CASE WHEN leads.lead_type_id = ? THEN 1 ELSE 0 END), 0) as type_n,
                COALESCE(SUM(CASE WHEN leads.lead_type_id = ? AND lead_pipeline_stages.code = 'won' THEN 1 ELSE 0 END), 0) as type_won",
                [$sourceId, $sourceId, $typeId, $typeId]
            )
            ->first();

        $totalClosed = (int) ($stats->total_closed ?? 0);
        $totalWon = (int) ($stats->total_won ?? 0);

        $baseRate = $totalClosed >= 5
            ? max(0.05, min(0.95, $totalWon / $totalClosed))
            : $stageProbability;

        $logOdds = self::logit($baseRate);
        $drivers = [];

        $stageShift = 0.55 * (self::logit($stageProbability) - self::logit($baseRate));
        $logOdds += $stageShift;

        if (abs($stageShift) >= 0.15) {
            $drivers[] = $stageShift > 0 ? 'advanced_pipeline_stage' : 'early_pipeline_stage';
        }

        if ($sourceId > 0 && $totalClosed >= 5) {
            $shrunkSource = self::shrunkRate((int) ($stats->source_won ?? 0), (int) ($stats->source_n ?? 0), $baseRate);
            $sourceLift = self::logit($shrunkSource) - self::logit($baseRate);
            $logOdds += $sourceLift;

            if (abs($sourceLift) >= 0.1) {
                $drivers[] = $sourceLift > 0 ? 'high_converting_source' : 'low_converting_source';
            }
        }

        if ($typeId > 0 && $totalClosed >= 5) {
            $shrunkType = self::shrunkRate((int) ($stats->type_won ?? 0), (int) ($stats->type_n ?? 0), $baseRate);
            $typeLift = self::logit($shrunkType) - self::logit($baseRate);
            $logOdds += $typeLift;

            if (abs($typeLift) >= 0.1) {
                $drivers[] = $typeLift > 0 ? 'strong_deal_type' : 'challenging_deal_type';
            }
        }

        $activityCount = (int) $lead->activities()->count();

        if ($activityCount >= 3) {
            $logOdds += 0.35;
            $drivers[] = 'high_activity_velocity';
        } elseif ($activityCount >= 1) {
            $logOdds += 0.15;
            $drivers[] = 'active_engagement';
        }

        $hasEmail = ! empty(data_get($lead->person, 'emails.0.value'));
        $hasPhone = ! empty(data_get($lead->person, 'contact_numbers.0.value'));

        if ($hasEmail && $hasPhone) {
            $logOdds += 0.20;
            $drivers[] = 'verified_multichannel_contact';
        } elseif (! $hasEmail && ! $hasPhone) {
            $logOdds -= 0.30;
            $drivers[] = 'missing_contact_channels';
        }

        $prob = max(0.01, min(0.99, self::sigmoid($logOdds)));

        if ($drivers === []) {
            $drivers[] = 'baseline_pipeline_prior';
        }

        return $this->formatResult((int) $lead->id, $prob, $leadValue, $drivers);
    }

    private static function shrunkRate(int $successes, int $trials, float $prior): float
    {
        $rate = ($successes + self::PRIOR_PSEUDO_COUNT * $prior) / ($trials + self::PRIOR_PSEUDO_COUNT);

        return max(0.02, min(0.98, $rate));
    }

    private static function logit(float $p): float
    {
        $clamped = max(0.005, min(0.995, $p));

        return log($clamped / (1.0 - $clamped));
    }

    private static function sigmoid(float $x): float
    {
        return 1.0 / (1.0 + exp(-$x));
    }

    /**
     * @param  array<int, string>  $drivers
     * @return array{lead_id: int, win_probability: float, expected_value: float, grade: string, drivers: array<int, string>}
     */
    private function formatResult(int $leadId, float $probability, float $leadValue, array $drivers): array
    {
        $roundedProb = round($probability, 4);
        $grade = match (true) {
            $roundedProb >= 0.70 => 'A',
            $roundedProb >= 0.45 => 'B',
            $roundedProb >= 0.25 => 'C',
            default => 'D',
        };

        return [
            'lead_id' => $leadId,
            'win_probability' => $roundedProb,
            'expected_value' => round(max(0.0, $leadValue) * $roundedProb, 2),
            'grade' => $grade,
            'drivers' => $drivers,
        ];
    }
}
