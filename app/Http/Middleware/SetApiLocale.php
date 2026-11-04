<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * The app's language for API answers (validation errors, names, labels): the phone's
     * Accept-Language when it is one we speak, else the signed-in user's choice.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('baydaq.locales'));
        $asked = $request->header('Accept-Language') ? $request->getPreferredLanguage($supported) : null;
        $locale = in_array($asked, $supported, true) ? $asked : $request->user()?->locale;

        App::setLocale(is_string($locale) && in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
