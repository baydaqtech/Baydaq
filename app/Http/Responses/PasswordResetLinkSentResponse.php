<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetLinkSentResponse implements FailedPasswordResetLinkRequestResponse
{
    /**
     * Whatever went wrong (unknown email, throttled), reply as if the link was sent.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = trans(Password::RESET_LINK_SENT);

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
