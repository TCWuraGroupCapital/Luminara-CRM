<?php

namespace Webkul\Tma\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TmaAppController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedLocale = $request->query('locale');

        if (is_string($requestedLocale) && $requestedLocale !== '') {
            $available = array_keys((array) config('app.available_locales', ['en' => 'English']));

            if (in_array($requestedLocale, $available, true)) {
                app()->setLocale($requestedLocale);
            }
        }

        return response()->view('tma::app');
    }
}
