<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\WorkspaceManager;

class WorkspacePruneCommand extends Command
{
    protected $signature = 'luminara:workspace:prune';

    protected $description = 'Remove sign-ups whose email address was never confirmed, freeing the address and the slot';

    public function handle(WorkspaceManager $manager): int
    {
        $manager->switchToLandlord();

        /**
         * An unconfirmed sign-up has no database, only a registry row and a reserved address.
         */
        $stale = Workspace::query()
            ->where('status', Workspace::STATUS_UNVERIFIED)
            ->where('created_at', '<', now()->subHours(Workspace::VERIFICATION_HOURS))
            ->get();

        $stale->each(fn (Workspace $workspace) => $workspace->delete());

        $this->info("Removed {$stale->count()} unconfirmed sign-up(s).");

        return self::SUCCESS;
    }
}
