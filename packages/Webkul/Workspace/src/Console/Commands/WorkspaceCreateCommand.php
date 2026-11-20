<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Support\WorkspaceProvisioner;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceCreateCommand extends Command
{
    protected $signature = 'luminara:workspace:create
                            {name : Business name}
                            {email : Owner email address}
                            {--owner-name= : Owner display name (defaults to the business name)}
                            {--password= : Owner password (or set LUMINARA_WORKSPACE_PASSWORD)}';

    protected $description = 'Create a workspace (its own database and an owner account) for one business';

    public function handle(WorkspaceManager $manager, WorkspaceProvisioner $provisioner): int
    {
        if (! $manager->enabled()) {
            $this->error('Workspaces are off. Set LUMINARA_WORKSPACES=true first.');

            return self::FAILURE;
        }

        $manager->switchToLandlord();

        $email = (string) $this->argument('email');
        $envPassword = getenv('LUMINARA_WORKSPACE_PASSWORD');
        $password = (string) ($this->option('password') ?: (is_string($envPassword) ? $envPassword : ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strtolower($email) === 'admin@example.com') {
            $this->error('A valid owner email is required.');

            return self::FAILURE;
        }

        if (strlen($password) < 12) {
            $this->error('The owner password must be at least 12 characters.');

            return self::FAILURE;
        }

        if ($manager->emailTakenElsewhere($email, null)) {
            $this->error("[{$email}] already signs in to another workspace.");

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');

        $workspace = $provisioner->register($name, (string) ($this->option('owner-name') ?: $name), $email, $password);

        $provisioner->provision($workspace);

        $workspace->refresh();

        if ($workspace->status !== Workspace::STATUS_ACTIVE) {
            $this->error('Provisioning failed: '.$workspace->error);

            return self::FAILURE;
        }

        $this->info("Workspace [{$workspace->slug}] is ready for [{$email}].");

        return self::SUCCESS;
    }
}
