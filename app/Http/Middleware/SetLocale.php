<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * How long the visitor's language choice is remembered, in minutes (one year).
     */
    private const COOKIE_MINUTES = 525600;

    /**
     * Pick the request's language: an explicit ?lang= wins and is remembered,
     * then the signed-in user's choice, then the session, then the cookie.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if (is_string($requested) && $this->isSupported($requested)) {
            $this->remember($request, $requested);
            $locale = $requested;
        } else {
            $locale = $request->user()?->locale
                ?? $request->session()->get('locale')
                ?? $request->cookie('locale');
        }

        App::setLocale(is_string($locale) && $this->isSupported($locale) ? $locale : config('app.locale'));

        return $next($request);
    }

    private function isSupported(string $locale): bool
    {
        return array_key_exists($locale, config('baydaq.locales'));
    }

    private function remember(Request $request, string $locale): void
    {
        $request->session()->put('locale', $locale);
        Cookie::queue('locale', $locale, self::COOKIE_MINUTES);

        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }
    }
}
