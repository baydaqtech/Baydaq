<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    use PasswordValidationRules;

    /**
     * The forced "choose a new password" screen for flagged accounts.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect($request->user()->homeUrl());
        }

        return Inertia::render('Auth/ChangePassword', [
            'copy' => ['account' => trans('account')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect($user->homeUrl());
        }

        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect($user->homeUrl());
    }
}
