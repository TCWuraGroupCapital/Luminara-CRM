<?php

namespace Webkul\Agent\Actions\Activities;

use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;

class CompleteActivity extends ActivityAction
{
    public function name(): string
    {
        return 'activities_complete';
    }

    public function summary(): string
    {
        return 'Mark an activity done and optionally append an outcome note.';
    }

    public function permission(): string
    {
        return 'activities.edit';
    }

    public function rules(): array
    {
        return [
            'activity_id' => ['required', 'integer', 'min:1'],
            'outcome_note' => ['nullable', 'string', 'max:2000'],
            'outcome_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function subject(array $params): array
    {
        /** @var Activity $activity */
        $activity = $this->scopedActivities()->whereKey($params['activity_id'])->firstOrFail();

        return [$this->snapshotActivity($activity)];
    }

    public function handle(array $params): array
    {
        /** @var Activity $activity */
        $activity = $this->scopedActivities()->whereKey($params['activity_id'])->firstOrFail();

        $comment = $activity->getAttribute('comment');
        $rawNote = $params['outcome_note'] ?? $params['outcome_comment'] ?? null;

        if (is_string($rawNote) && trim($rawNote) !== '') {
            $note = trim($rawNote);
            $comment = $comment ? $comment."\n\nOutcome: ".$note : 'Outcome: '.$note;
        }

        Event::dispatch('activity.update.before', $activity->getKey());

        /** @var Activity $updated */
        $updated = $this->activityRepository->update([
            'is_done' => 1,
            'comment' => $comment,
        ], $activity->getKey());

        Event::dispatch('activity.update.after', $updated);

        return ['activity' => $this->present($updated->refresh())];
    }
}
