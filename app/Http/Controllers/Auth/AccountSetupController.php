<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountSetupController extends Controller
{
    use PasswordValidationRules;

    /**
     * The page behind the "your account is ready" email: the new user picks their first password.
     */
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('Auth/AccountSetup', [
            'token' => $token,
            'email' => $request->query('email'),
            'copy' => ['account' => trans('account')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $signedIn = null;

        $status = Password::broker('invites')->reset(
            $credentials,
            function (User $user, string $password) use (&$signedIn): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $signedIn = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || $signedIn === null) {
            throw ValidationException::withMessages(['email' => trans('account.setup.invalid_link')]);
        }

        Auth::login($signedIn);
        $request->session()->regenerate();

        return redirect($signedIn->homeUrl());
    }
}
