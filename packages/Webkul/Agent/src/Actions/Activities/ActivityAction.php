<?php

namespace Webkul\Agent\Actions\Activities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

abstract class ActivityAction extends AgentAction
{
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
    ) {}

    /**
     * @return Builder<Activity>
     */
    protected function scopedActivities(): Builder
    {
        /** @var Builder<Activity> $query */
        $query = $this->activityRepository->getModel()->newQuery();
        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds === null) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($userIds) {
            $sub->whereIn('activities.user_id', $userIds)
                ->orWhereExists(function ($exists) use ($userIds) {
                    $exists->select(DB::raw(1))
                        ->from('activity_participants')
                        ->whereColumn('activity_participants.activity_id', 'activities.id')
                        ->whereIn('activity_participants.user_id', $userIds);
                });
        });
    }

    protected function scopedLead(int $leadId): Lead
    {
        /** @var Lead $lead */
        $lead = bouncer()
            ->scopeToAuthorizedUsers($this->leadRepository->getModel()->newQuery())
            ->whereKey($leadId)
            ->firstOrFail();

        return $lead;
    }

    protected function scopedPerson(int $personId): Person
    {
        /** @var Person $person */
        $person = bouncer()
            ->scopeToAuthorizedUsers($this->personRepository->getModel()->newQuery())
            ->whereKey($personId)
            ->firstOrFail();

        return $person;
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function snapshotActivity(Activity $activity): array
    {
        return [
            'id' => $activity->getKey(),
            'title' => $activity->getAttribute('title'),
            'type' => $activity->getAttribute('type'),
            'is_done' => (bool) $activity->getAttribute('is_done'),
            'owner_id' => $activity->getAttribute('user_id'),
            'updated_at' => $activity->getAttribute('updated_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Activity $activity): array
    {
        return [
            'id' => $activity->getKey(),
            'title' => $activity->getAttribute('title'),
            'type' => $activity->getAttribute('type'),
            'comment' => $activity->getAttribute('comment'),
            'location' => $activity->getAttribute('location'),
            'schedule_from' => $activity->getAttribute('schedule_from')?->toIso8601String(),
            'schedule_to' => $activity->getAttribute('schedule_to')?->toIso8601String(),
            'is_done' => (bool) $activity->getAttribute('is_done'),
            'owner_id' => $activity->getAttribute('user_id'),
            'created_at' => $activity->getAttribute('created_at')?->toIso8601String(),
        ];
    }
}
