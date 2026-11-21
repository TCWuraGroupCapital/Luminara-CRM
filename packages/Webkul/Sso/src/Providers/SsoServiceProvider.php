<?php

namespace Webkul\Sso\Providers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Sso\Listeners\BlockPasswordSignIn;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'sso');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sso');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Event::listen(Attempting::class, [BlockPasswordSignIn::class, 'handle']);

        Event::listen('admin.sessions.login.form_controls.after', static function (ViewRenderEventManager $viewRenderEventManager): void {
            if (config('sso.enabled')) {
                $viewRenderEventManager->addTemplate('sso::partials.login-link');
            }
        });
    }
}
