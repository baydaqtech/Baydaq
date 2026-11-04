<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    /**
     * Send the team to the admin and clients to their portal (after the password, or after the
     * second step when two-step sign-in is on).
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        // The password was just typed: pages that ask for it again (two-step sign-in setup) don't.
        $request->session()->passwordConfirmed();

        return redirect()->intended($request->user()->homeUrl());
    }
}
