<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class Locale
{
    /**
     * The middleware instance.
     *
     * @return void
     */
    public function __construct(
        Application $app,
        Request $request
    ) {
        $this->app = $app;

        $this->request = $request;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $configured = core()->getConfigData('general.general.locale_settings.locale');

        /**
         * Only a locale the app ships is applied. A stored value outside that list (a bad
         * import, or anything posted to the settings form) used to make every page, the sign-in
         * page included, fail with "Invalid characters present in locale".
         */
        if (is_string($configured) && array_key_exists($configured, (array) config('app.available_locales'))) {
            app()->setLocale($configured);
        }

        return $next($request);
    }
}
