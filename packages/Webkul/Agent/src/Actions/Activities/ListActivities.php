<?php

namespace Webkul\Agent\Actions\Activities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;

class ListActivities extends ActivityAction
{
    public function name(): string
    {
        return 'activities_list';
    }

    public function summary(): string
    {
        return 'List activities inside the caller\'s scope, optionally filtered by lead_id, person_id, or open status.';
    }

    public function permission(): string
    {
        return 'activities';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'person_id' => ['nullable', 'integer', 'min:1'],
            'only_open' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function handle(array $params): array
    {
        $lead = isset($params['lead_id']) ? $this->scopedLead((int) $params['lead_id']) : null;
        $person = isset($params['person_id']) ? $this->scopedPerson((int) $params['person_id']) : null;

        $activities = $this->scopedActivities()
            ->where('activities.type', '!=', 'system')
            ->when($lead !== null, fn (Builder $q) => $q->whereExists(fn ($sub) => $sub
                ->select(DB::raw(1))
                ->from('lead_activities')
                ->whereColumn('lead_activities.activity_id', 'activities.id')
                ->where('lead_activities.lead_id', $lead->getKey())))
            ->when($person !== null, fn (Builder $q) => $q->whereExists(fn ($sub) => $sub
                ->select(DB::raw(1))
                ->from('person_activities')
                ->whereColumn('person_activities.activity_id', 'activities.id')
                ->where('person_activities.person_id', $person->getKey())))
            ->when(! empty($params['only_open']), fn (Builder $q) => $q->where('is_done', 0))
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return [
            'activities' => $activities->map(fn (Activity $activity): array => $this->present($activity))->all(),
        ];
    }
}
