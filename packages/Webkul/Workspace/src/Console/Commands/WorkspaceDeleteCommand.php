<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Support\WorkspaceProvisioner;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceDeleteCommand extends Command
{
    protected $signature = 'luminara:workspace:delete
                            {slug : Workspace slug (see luminara:workspace:list)}
                            {--confirm= : Repeat the slug to confirm; nothing is deleted without it}';

    protected $description = 'Permanently delete a workspace: its database, files and sign-ins. Cannot be undone.';

    public function handle(WorkspaceManager $manager, WorkspaceProvisioner $provisioner): int
    {
        $manager->switchToLandlord();

        $slug = (string) $this->argument('slug');

        $workspace = Workspace::query()->where('slug', $slug)->first();

        if ($workspace === null) {
            $this->error('No workspace has that slug.');

            return self::FAILURE;
        }

        if ($this->option('confirm') !== $slug) {
            $this->error("This permanently deletes [{$workspace->name}] and everything in it. Run luminara:backup inside it first if you may need the data, then repeat with --confirm={$slug}.");

            return self::FAILURE;
        }

        /**
         * A workspace still in use has to be suspended first, so deleting is always two
         * deliberate steps for a live business.
         */
        if ($workspace->status === Workspace::STATUS_ACTIVE) {
            $this->error("Workspace [{$slug}] is active. Suspend it first with luminara:workspace:suspend {$slug}.");

            return self::FAILURE;
        }

        $provisioner->destroy($workspace);

        $this->info("Workspace [{$slug}] was deleted.");

        return self::SUCCESS;
    }
}
