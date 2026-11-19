<?php

namespace Webkul\Agent\Actions\Activities;

use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;

class LogNote extends ActivityAction
{
    public function name(): string
    {
        return 'activities_log_note';
    }

    public function summary(): string
    {
        return 'Log a completed note on a lead and/or contact inside the caller\'s scope.';
    }

    public function permission(): string
    {
        return 'activities.create';
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
            'title' => ['nullable', 'string', 'max:255'],
            'lead_id' => ['nullable', 'integer', 'min:1', 'required_without:person_id'],
            'person_id' => ['nullable', 'integer', 'min:1', 'required_without:lead_id'],
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

        Event::dispatch('activity.create.before');

        /** @var Activity $activity */
        $activity = $this->activityRepository->create([
            'type' => 'note',
            'title' => $params['title'] ?? 'Agent note',
            'comment' => $params['comment'],
            'is_done' => 1,
            'user_id' => auth()->guard('user')->id(),
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
