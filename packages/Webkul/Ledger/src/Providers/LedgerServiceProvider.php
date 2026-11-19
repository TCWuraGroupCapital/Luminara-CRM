<?php

namespace Webkul\Ledger\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Ledger\Console\Commands\SealEpochCommand;
use Webkul\Ledger\Console\Commands\VerifyLedgerCommand;
use Webkul\Ledger\Contracts\AnchorDriver;
use Webkul\Ledger\Listeners\CrmEntityBlockSubscriber;
use Webkul\Ledger\Support\LedgerAppender;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Ledger\Support\LedgerVerifier;
use Webkul\Ledger\Support\LocalSignedAnchorDriver;

class LedgerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'ledger');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ledger');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        Event::subscribe(CrmEntityBlockSubscriber::class);

        if (class_exists(Breadcrumbs::class) && ! Breadcrumbs::exists('ledger.index')) {
            Breadcrumbs::for('ledger.index', function (BreadcrumbTrail $trail): void {
                if (Breadcrumbs::exists('settings')) {
                    $trail->parent('settings');
                }

                $trail->push(trans('ledger::app.admin.title'), route('admin.ledger.index'));
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SealEpochCommand::class,
                VerifyLedgerCommand::class,
            ]);
        }

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/ledger.php', 'ledger');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->app->singleton(LedgerSigner::class);

        $this->app->singleton(LedgerAppender::class);

        $this->app->singleton(LedgerVerifier::class);

        $this->app->bind(AnchorDriver::class, function ($app): AnchorDriver {
            $driverKey = (string) config('ledger.anchor_driver', 'local');
            $drivers = (array) config('ledger.drivers', []);
            $driverClass = $drivers[$driverKey] ?? LocalSignedAnchorDriver::class;

            /** @var AnchorDriver $driver */
            $driver = $app->make($driverClass);

            return $driver;
        });
    }
}
