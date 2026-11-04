<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * module:hr — the tenant must have bought the module. A lapsed subscription still opens
 * read-only: reads pass, writes are refused with a clear message.
 */
class EnsureModuleIsActive
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $module = Module::from($module);
        $subscription = Subscription::query()->forModule($module)->latest('starts_on')->first();

        if ($subscription === null) {
            abort(403, __('suite.modules.inactive'));
        }

        if (! $subscription->isActive() && ! $request->isMethodSafe()) {
            abort(403, __('suite.modules.read_only', ['module' => $module->label()]));
        }

        return $next($request);
    }
}
