<?php

namespace Webkul\Privacy\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Privacy\Console\Commands\PrivacyEraseCommand;
use Webkul\Privacy\Console\Commands\PrivacyExportCommand;

class PrivacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'privacy');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'privacy');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        Event::listen('admin.contact.persons.view.right.after', static function (ViewRenderEventManager $viewRenderEventManager): void {
            $viewRenderEventManager->addTemplate('privacy::person-panel');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                PrivacyExportCommand::class,
                PrivacyEraseCommand::class,
            ]);
        }
    }
}
