<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class TwoFactorController extends Controller
{
    /**
     * Two-step sign-in for the signed-in user. Turning it on, confirming the first code and the
     * recovery codes go through Fortify's routes; this page shows the state and the QR code
     * while it waits for the first code. Recovery codes are shown right after they are made,
     * or on request.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
        $codesJustMade = in_array($request->session()->get('status'), [Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED, Fortify::RECOVERY_CODES_GENERATED], true);

        return Inertia::render('Auth/TwoFactor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'required' => $user->mustUseTwoFactor(),
            'qr' => $pending ? 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg()) : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $codesJustMade && $user->hasTwoFactorEnabled()
                ? $user->recoveryCodes()
                : Inertia::optional(fn (): array => $user->hasTwoFactorEnabled() ? $user->recoveryCodes() : []),
            'home' => $user->homeUrl(),
            'copy' => ['account' => trans('account')],
        ]);
    }
}
