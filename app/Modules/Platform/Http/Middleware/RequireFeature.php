<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Ports\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * feature:hr.tasks or feature:mobile.tasks: the route only works when the client's package has
 * the feature. The app gets a machine-readable code so it can hide the screen.
 */
class RequireFeature
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $on = str_starts_with($feature, 'mobile.')
            ? in_array($feature, $this->entitlements->mobileFeatures(), true)
            : $this->entitlements->hasFeature($feature);

        if (! $on) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('suite.plans.feature_off'), 'code' => 'feature_disabled', 'feature' => $feature], 403);
            }

            abort(403, __('suite.plans.feature_off'));
        }

        return $next($request);
    }
}
