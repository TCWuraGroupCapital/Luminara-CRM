<?php

namespace Webkul\Agent\Actions\Activities;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;

class ScheduleActivity extends ActivityAction
{
    public function name(): string
    {
        return 'activities_schedule';
    }

    public function summary(): string
    {
        return 'Schedule a call, meeting, or lunch and link it to a lead or contact inside the caller\'s scope.';
    }

    public function permission(): string
    {
        return 'activities.create';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:call,meeting,lunch'],
            'schedule_from' => ['required', 'date'],
            'schedule_to' => ['nullable', 'date', 'after_or_equal:schedule_from'],
            'location' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'person_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function subject(array $params): array
    {
        $rows = [];

        if (isset($params['lead_id'])) {
            $lead = $this->scopedLead((int) $params['lead_id']);
            $rows[] = [
                'entity' => 'lead',
                'id' => $lead->getKey(),
                'title' => $lead->getAttribute('title'),
                'updated_at' => $lead->getAttribute('updated_at')?->toIso8601String(),
            ];
        }

        if (isset($params['person_id'])) {
            $person = $this->scopedPerson((int) $params['person_id']);
            $rows[] = [
                'entity' => 'person',
                'id' => $person->getKey(),
                'name' => $person->getAttribute('name'),
                'updated_at' => $person->getAttribute('updated_at')?->toIso8601String(),
            ];
        }

        return $rows;
    }

    public function handle(array $params): array
    {
        $lead = isset($params['lead_id']) ? $this->scopedLead((int) $params['lead_id']) : null;
        $person = isset($params['person_id']) ? $this->scopedPerson((int) $params['person_id']) : null;
        $userId = auth()->guard('user')->id();

        $from = Carbon::parse((string) $params['schedule_from']);
        $to = ! empty($params['schedule_to'])
            ? Carbon::parse((string) $params['schedule_to'])
            : $from->copy()->addMinutes(30);

        Event::dispatch('activity.create.before');

        /** @var Activity $activity */
        $activity = $this->activityRepository->create([
            'type' => $params['type'],
            'title' => $params['title'],
            'schedule_from' => $from->toDateTimeString(),
            'schedule_to' => $to->toDateTimeString(),
            'location' => $params['location'] ?? null,
            'comment' => $params['comment'] ?? null,
            'is_done' => 0,
            'user_id' => $userId,
            'participants' => [
                'users' => $userId !== null ? [$userId] : [],
                'persons' => $person !== null ? [$person->getKey()] : [],
            ],
        ]);

        if ($lead !== null) {
            $lead->activities()->syncWithoutDetaching([$activity->getKey()]);
        }

        if ($person !== null) {
            $person->activities()->syncWithoutDetaching([$activity->getKey()]);
        }

        Event::dispatch('activity.create.after', $activity);

        return ['activity' => $this->present($activity->refresh())];
    }
}
