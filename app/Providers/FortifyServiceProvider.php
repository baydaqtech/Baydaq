<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetLinkSentResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);

        // Answer "no such email" exactly like "link sent", so the form can't be used to find accounts.
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkSentResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn (): Response => $this->authPage('Auth/Login'));
        Fortify::requestPasswordResetLinkView(fn (): Response => $this->authPage('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request): Response => $this->authPage('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Fortify leaves the forgot-password form unthrottled; limit sends per visitor.
        RateLimiter::for('fortify-guest', function (Request $request) {
            return $request->isMethod('post') && $request->is('forgot-password')
                ? Limit::perMinute(5)->by($request->ip())
                : Limit::none();
        });
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function authPage(string $component, array $props = []): Response
    {
        return Inertia::render($component, [
            ...$props,
            'copy' => ['account' => trans('account')],
        ]);
    }
}
