<?php

namespace Webkul\Workspace\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;
use Webkul\Core\ViewRenderEventManager;
use Webkul\User\Models\User;
use Webkul\Workspace\Console\Commands\WorkspaceCreateCommand;
use Webkul\Workspace\Console\Commands\WorkspaceDeleteCommand;
use Webkul\Workspace\Console\Commands\WorkspaceListCommand;
use Webkul\Workspace\Console\Commands\WorkspacePruneCommand;
use Webkul\Workspace\Console\Commands\WorkspaceRunCommand;
use Webkul\Workspace\Console\Commands\WorkspaceSuspendCommand;
use Webkul\Workspace\Http\Middleware\ResolveWorkspace;
use Webkul\Workspace\Listeners\SyncWorkspaceMembers;
use Webkul\Workspace\Models\Workspace;
use Webkul\Workspace\Support\PublicLinks;
use Webkul\Workspace\WorkspaceManager;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceManager::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'workspace');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'workspace');

        if ($this->app->runningInConsole()) {
            $this->commands([
                WorkspaceCreateCommand::class,
                WorkspaceDeleteCommand::class,
                WorkspaceListCommand::class,
                WorkspacePruneCommand::class,
                WorkspaceRunCommand::class,
                WorkspaceSuspendCommand::class,
            ]);
        }

        /**
         * Resolved here so the landlord connection exists before anything asks for it.
         */
        $manager = $this->app->make(WorkspaceManager::class);

        /**
         * Everything below is registered unconditionally and checks `enabled()` when it runs, so
         * the switch can be read from configuration at any time. With workspaces off each hook
         * returns at once and the application behaves as a single-business install.
         */
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->registerMiddleware($router);

        $this->registerPublicLinks($router);

        $this->registerQueueContext($manager);

        $this->registerMemberSync();

        $this->registerSchedule();

        $this->registerConsoleWorkspace($manager);

        Event::listen('admin.sessions.login.form_controls.after', static function (ViewRenderEventManager $viewRenderEventManager): void {
            if (config('workspaces.enabled') && in_array(config('workspaces.signup'), ['open', 'invite'], true)) {
                $viewRenderEventManager->addTemplate('workspace::partials.login-link');
            }
        });

        Event::listen('admin.dashboard.index.content.before', static function (ViewRenderEventManager $viewRenderEventManager): void {
            if (config('workspaces.enabled')) {
                $viewRenderEventManager->addTemplate('workspace::partials.getting-started');
            }
        });
    }

    /**
     * The workspace has to be chosen after the session is readable and before route bindings,
     * two-factor checks, token checks or controllers touch the database.
     *
     * It is attached to every matched route rather than to the `web` and `api` groups, because
     * several packages (the agent API among them) define routes outside those groups. Its place
     * in the stack comes from the router's priority list, written directly: going through the
     * HTTP kernel would re-sync the kernel's own groups to the router and silently drop the
     * middleware other packages have pushed, two-factor enforcement included.
     */
    private function registerMiddleware(Router $router): void
    {
        Event::listen(RouteMatched::class, static function (RouteMatched $event): void {
            $event->route->middleware(ResolveWorkspace::class);
        });

        $prioritise = static function () use ($router): void {
            $priority = array_values(array_diff($router->middlewarePriority, [ResolveWorkspace::class]));

            $index = array_search(StartSession::class, $priority, true);

            array_splice($priority, $index === false ? 0 : $index + 1, 0, [ResolveWorkspace::class]);

            $router->middlewarePriority = $priority;
        };

        $prioritise();

        /**
         * In console and test runs the HTTP kernel is built after the providers have booted and
         * overwrites the router's priority list, so the entry is put back once that has happened.
         */
        $this->app->afterResolving(Kernel::class, $prioritise);
    }

    /**
     * Public pages (web forms, receipts, webhooks, experiment beacons) get an address that names
     * the business, and links to them generated inside a workspace use that address.
     */
    private function registerPublicLinks(Router $router): void
    {
        $this->app->singleton(PublicLinks::class);

        $this->app->booted(function () use ($router): void {
            if (! $this->app->routesAreCached()) {
                $this->app->make(PublicLinks::class)->registerRoutes($router);
            }
        });

        URL::formatPathUsing(fn (string $path): string => $this->app->make(PublicLinks::class)->path($path));
    }

    /**
     * A queued job runs in the workspace it was dispatched from, and the worker returns to
     * where it was afterwards so the next job can never inherit it.
     */
    private function registerQueueContext(WorkspaceManager $manager): void
    {
        Queue::createPayloadUsing(static fn (): array => $manager->enabled() ? ['luminara_workspace' => $manager->current()?->id] : []);

        $stack = [];

        Event::listen(JobProcessing::class, static function (JobProcessing $event) use ($manager, &$stack): void {
            if (! $manager->enabled()) {
                return;
            }

            $stack[] = $manager->current();

            $id = $event->job->payload()['luminara_workspace'] ?? null;

            $manager->switchToLandlord();

            if ($id !== null && ($workspace = Workspace::query()->active()->find($id)) !== null) {
                $manager->switchTo($workspace);
            }
        });

        $restore = static function () use ($manager, &$stack): void {
            if (! $manager->enabled()) {
                return;
            }

            $previous = array_pop($stack);

            $previous === null ? $manager->switchToLandlord() : $manager->switchTo($previous);
        };

        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }

    private function registerMemberSync(): void
    {
        User::saving(fn (User $user) => $this->app->make(SyncWorkspaceMembers::class)->saving($user));
        User::saved(fn (User $user) => $this->app->make(SyncWorkspaceMembers::class)->saved($user));
        User::deleted(fn (User $user) => $this->app->make(SyncWorkspaceMembers::class)->deleted($user));
    }

    /**
     * The stock schedule only reaches the main database; these repeat it for every workspace.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('luminara:workspace:run', ['luminara:ledger-seal'])->hourlyAt(7)->withoutOverlapping(20)->when(static fn (): bool => (bool) config('workspaces.enabled'));
            /**
             * Each business reads replies from its own mailbox, when it has saved one.
             */
            $schedule->command('luminara:workspace:run', ['inbound-emails:process'])->everyFiveMinutes()->withoutOverlapping(10)->when(static fn (): bool => (bool) config('workspaces.enabled'));
            $schedule->command('luminara:workspace:prune')->hourlyAt(37)->when(static fn (): bool => (bool) config('workspaces.enabled'));
            $schedule->command('luminara:workspace:run', ['luminara:backup'])->dailyAt('02:10')->withoutOverlapping(60)->when(static fn (): bool => (bool) config('workspaces.enabled'));
        });
    }

    /**
     * `LUMINARA_WORKSPACE=<slug> php artisan ...` runs any command inside that workspace.
     */
    private function registerConsoleWorkspace(WorkspaceManager $manager): void
    {
        $slug = getenv('LUMINARA_WORKSPACE');

        if (! $manager->enabled() || ! $this->app->runningInConsole() || ! is_string($slug) || $slug === '') {
            return;
        }

        try {
            $workspace = Workspace::query()->active()->where('slug', $slug)->first();
        } catch (Throwable) {
            return;
        }

        if ($workspace === null) {
            throw new RuntimeException("Unknown or inactive workspace [{$slug}].");
        }

        $manager->switchTo($workspace);
    }
}
