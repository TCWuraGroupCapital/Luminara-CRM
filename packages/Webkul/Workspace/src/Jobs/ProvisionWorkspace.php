<?php

namespace Webkul\Workspace\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Support\WorkspaceProvisioner;

/**
 * Builds a new workspace's database off the request, because running every migration takes
 * longer than a sign-up form should wait.
 */
class ProvisionWorkspace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $workspaceId) {}

    public function handle(WorkspaceProvisioner $provisioner): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);

        if ($workspace !== null) {
            $provisioner->provision($workspace);
        }
    }
}
