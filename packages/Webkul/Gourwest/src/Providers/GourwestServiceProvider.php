<?php

namespace Webkul\Gourwest\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\ServiceProvider;
use Webkul\Gourwest\Console\Commands\LoadDatasetCommand;
use Webkul\Gourwest\Console\Commands\RunWarrantyBenchmarkCommand;
use Webkul\Gourwest\Console\Commands\SeedGourwestCommand;

class GourwestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'gourwest');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'gourwest');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        ValidateCsrfToken::except([
            'api/gourwest/webhooks/*',
        ]);

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('gourwest')) {
            Breadcrumbs::for('gourwest', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('dashboard')) {
                    $trail->parent('dashboard');
                }

                $trail->push(trans('gourwest::app.index.title'), route('admin.gourwest.index'));
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedGourwestCommand::class,
                LoadDatasetCommand::class,
                RunWarrantyBenchmarkCommand::class,
            ]);
        }

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/gourwest.php', 'gourwest');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }
}
