<?php

namespace Webkul\Workspace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceRunCommand extends Command
{
    protected $signature = 'luminara:workspace:run
                            {artisan : The artisan command line to run, quoted, for example "migrate --force"}
                            {--workspace=* : Workspace slug (repeatable). Default: every active workspace}';

    protected $description = 'Run an artisan command inside one, several or all workspaces';

    public function handle(WorkspaceManager $manager): int
    {
        if (! $manager->enabled()) {
            $this->line('Workspaces are off; nothing to do.');

            return self::SUCCESS;
        }

        $manager->switchToLandlord();

        $slugs = (array) $this->option('workspace');

        $workspaces = Workspace::query()->active()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get();

        $failed = 0;

        foreach ($workspaces as $workspace) {
            try {
                $exit = $manager->run($workspace, fn (): int => Artisan::call((string) $this->argument('artisan'), [], $this->output));
            } catch (Throwable $exception) {
                report($exception);

                $this->error("[{$workspace->slug}] ".$exception->getMessage());

                $exit = 1;
            }

            $exit === 0 ? $this->info("[{$workspace->slug}] ok") : $failed++;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
