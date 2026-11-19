<?php

namespace Webkul\Tma\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Support\ServiceProvider;
use Webkul\Tma\Support\TonAnchorDriver;

class TmaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'tma');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'tma');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        config(['ledger.drivers.ton' => TonAnchorDriver::class]);

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('tma.pairing')) {
            Breadcrumbs::for('tma.pairing', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('tma::app.pairing.title'), route('admin.tma.pairing.index'));
            });
        }

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/tma.php', 'tma');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');
    }
}
