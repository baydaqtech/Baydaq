<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    /**
     * Admins and account owners turn on two-step sign-in before anything else, when the
     * deployment requires it (`baydaq.security.two_factor_required`).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasTwoFactorEnabled() && $user->mustUseTwoFactor()) {
            return redirect()->route('account.security')->with('status', 'two-factor-required');
        }

        return $next($request);
    }
}
