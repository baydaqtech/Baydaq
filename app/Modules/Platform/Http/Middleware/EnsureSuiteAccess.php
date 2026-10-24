<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Access\AccessResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * access:hr.employees.create (or access:a|b for any of) — the user must hold the key
 * somewhere in the tenant. Which records it covers is decided per query and per record.
 */
class EnsureSuiteAccess
{
    public function __construct(private AccessResolver $resolver) {}

    public function handle(Request $request, Closure $next, string $keys): Response
    {
        $access = $this->resolver->for($request->user());

        foreach (explode('|', $keys) as $key) {
            if ($access->allows($key)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
