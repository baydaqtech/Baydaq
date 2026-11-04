<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For token (mobile) requests: the tenant is the signed-in user's client. No scope segment;
 * the API works on the employee's own data and on what their roles reach.
 */
class ResolveApiTenant
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->client_id === null, 403);

        $this->tenant->set($user->client);

        return $next($request);
    }
}
