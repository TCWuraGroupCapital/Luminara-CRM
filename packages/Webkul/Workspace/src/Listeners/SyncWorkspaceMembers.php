<?php

namespace Webkul\Workspace\Listeners;

use Illuminate\Validation\ValidationException;
use Webkul\User\Models\User;
use Webkul\Workspace\Models\WorkspaceMember;
use Webkul\Workspace\WorkspaceManager;

/**
 * Keeps the sign-in directory in step with the users of each workspace, so a teammate an
 * administrator adds in Settings > Users can sign in, and one who is removed no longer can.
 */
class SyncWorkspaceMembers
{
    public function __construct(private WorkspaceManager $manager) {}

    /**
     * An email address may sign in to one workspace only.
     */
    public function saving(User $user): void
    {
        $email = $user->getAttribute('email');

        if (! $this->manager->enabled() || ! $user->isDirty('email') || ! is_string($email)) {
            return;
        }

        if ($this->manager->emailTakenElsewhere($email, $this->manager->current())) {
            throw ValidationException::withMessages([
                'email' => trans('workspace::app.validation.email-taken'),
            ]);
        }
    }

    public function saved(User $user): void
    {
        $workspace = $this->manager->current();
        $email = $user->getAttribute('email');

        if (! $this->manager->enabled() || $workspace === null || ! is_string($email)) {
            return;
        }

        $previous = $user->getOriginal('email');

        if (is_string($previous) && $previous !== $email) {
            WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('email', WorkspaceManager::normaliseEmail($previous))
                ->delete();
        }

        WorkspaceMember::query()->updateOrCreate(
            ['email' => WorkspaceManager::normaliseEmail($email)],
            ['workspace_id' => $workspace->id]
        );
    }

    public function deleted(User $user): void
    {
        $workspace = $this->manager->current();
        $email = $user->getAttribute('email');

        if (! $this->manager->enabled() || $workspace === null || ! is_string($email)) {
            return;
        }

        WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('email', WorkspaceManager::normaliseEmail($email))
            ->delete();
    }
}
