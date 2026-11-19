<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $adminPath = trim((string) config('app.admin_path', 'admin'), '/');

        ValidateCsrfToken::except([
            ($adminPath !== '' ? $adminPath.'/' : '').'mail/inbound-parse',
        ]);

        if (! $this->app->runningUnitTests()) {
            return;
        }

        ParallelTesting::setUpTestDatabase(function (string $database, int $token): void {
            Artisan::call('db:seed', ['--force' => true]);
        });
    }
}
