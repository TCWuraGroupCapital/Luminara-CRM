<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceListCommand extends Command
{
    protected $signature = 'luminara:workspace:list';

    protected $description = 'List workspaces with their status and member count';

    public function handle(WorkspaceManager $manager): int
    {
        $manager->switchToLandlord();

        $this->table(
            ['ID', 'Slug', 'Name', 'Status', 'Owner', 'Members', 'Created'],
            Workspace::query()->withCount('members')->orderBy('id')->get()->map(fn (Workspace $workspace) => [
                $workspace->id,
                $workspace->slug,
                $workspace->name,
                $workspace->status,
                $workspace->owner_email,
                $workspace->getAttribute('members_count'),
                (string) $workspace->getAttribute('created_at'),
            ])->all()
        );

        return self::SUCCESS;
    }
}
