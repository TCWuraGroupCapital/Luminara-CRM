<?php

namespace Webkul\Experiment\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Experiment\ExperimentManager;
use Webkul\Experiment\Listeners\ConversionAttributionListener;
use Webkul\Experiment\Repositories\ExperimentRepository;

class ExperimentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        include __DIR__.'/../Http/helpers.php';

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'experiment');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'experiment');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        ValidateCsrfToken::except([
            'experiments/track',
        ]);

        Blade::if('experiment', function (string $code, string $variant): bool {
            return app(ExperimentManager::class)->is($code, $variant);
        });

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('settings.experiments')) {
            Breadcrumbs::for('settings.experiments', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('experiment::app.index.title'), route('admin.settings.experiments.index'));
            });
        }

        Event::listen(
            'lead.create.after',
            [ConversionAttributionListener::class, 'handleLeadCreated']
        );

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->app->scoped(ExperimentManager::class, function ($app): ExperimentManager {
            return new ExperimentManager($app->make(ExperimentRepository::class));
        });
    }
}
