<?php

namespace Webkul\Legal\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Legal\Support\LegalDocuments;

class LegalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'legal');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'legal');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Event::listen('admin.sessions.login.form_controls.after', static function (ViewRenderEventManager $viewRenderEventManager): void {
            if (LegalDocuments::enabled()) {
                $viewRenderEventManager->addTemplate('legal::partials.links');
            }
        });
    }
}
