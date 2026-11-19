<?php

namespace Webkul\Agent\Providers;

use Anthropic\Client;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Support\ServiceProvider;
use Webkul\Agent\Console\Commands\IssueAgentTokenCommand;
use Webkul\Agent\Console\Commands\IssuePassportCommand;
use Webkul\Agent\Console\Commands\RunAgentActionCommand;
use Webkul\Agent\Support\ActionRegistry;
use Webkul\Agent\Support\Assistant;
use Webkul\Agent\Support\AssistantSettings;
use Webkul\Agent\Support\PassportGuard;
use Webkul\Agent\Support\PlaybookMemory;

class AgentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'agent');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'agent');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('agent.approvals')) {
            Breadcrumbs::for('agent.approvals', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('agent::app.approvals.title'), route('admin.agent.approvals.index'));
            });
        }

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('agent.briefing')) {
            Breadcrumbs::for('agent.briefing', function (BreadcrumbTrail $trail): void {
                $trail->push(trans('agent::app.briefing.title'), route('admin.agent.briefing.index'));
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                IssueAgentTokenCommand::class,
                IssuePassportCommand::class,
                RunAgentActionCommand::class,
            ]);
        }

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/agent.php', 'agent');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');

        $this->app->singleton(PassportGuard::class);

        $this->app->singleton(PlaybookMemory::class);

        $this->app->singleton(ActionRegistry::class, fn ($app) => new ActionRegistry(
            array_map(fn (string $class) => $app->make($class), config('agent.actions'))
        ));

        $this->app->when(Assistant::class)->needs(Client::class)->give(function () {
            $settings = AssistantSettings::resolve();

            return new Client(apiKey: $settings['api_key'], baseUrl: $settings['base_url']);
        });
    }
}
