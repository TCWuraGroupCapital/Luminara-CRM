<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceSuspendCommand extends Command
{
    protected $signature = 'luminara:workspace:suspend
                            {slug : Workspace slug (see luminara:workspace:list)}
                            {--resume : Bring a suspended workspace back}';

    protected $description = 'Suspend a workspace (nobody can sign in, its data is kept) or resume it';

    public function handle(WorkspaceManager $manager): int
    {
        $manager->switchToLandlord();

        $workspace = Workspace::query()->where('slug', (string) $this->argument('slug'))->first();

        if ($workspace === null) {
            $this->error('No workspace has that slug.');

            return self::FAILURE;
        }

        [$from, $to] = $this->option('resume')
            ? [Workspace::STATUS_SUSPENDED, Workspace::STATUS_ACTIVE]
            : [Workspace::STATUS_ACTIVE, Workspace::STATUS_SUSPENDED];

        if ($workspace->status !== $from) {
            $this->error("Workspace [{$workspace->slug}] is {$workspace->status}, not {$from}.");

            return self::FAILURE;
        }

        $workspace->update(['status' => $to]);

        /**
         * Signed-in sessions end on their next request: the workspace no longer resolves, and
         * the session is discarded rather than falling back to the main database.
         */
        $this->info("Workspace [{$workspace->slug}] is now {$to}.");

        return self::SUCCESS;
    }
}
