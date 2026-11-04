<?php

namespace App\Http\Middleware;

use App\Actions\Services\IssueApiKey;
use App\Enums\ProjectServiceStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baydaq's own service backends report usage with the project service's key (X-Service-Key).
 * A paused service still authenticates but is refused, so the client sees why nothing moves.
 */
class AuthenticateServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $projectService = IssueApiKey::resolve($request->header('X-Service-Key') ?? $request->bearerToken());

        abort_if($projectService === null, 401, 'Invalid service key.');
        abort_if($projectService->status !== ProjectServiceStatus::Active, 403, 'This service is paused.');

        $request->attributes->set('projectService', $projectService);

        return $next($request);
    }
}
