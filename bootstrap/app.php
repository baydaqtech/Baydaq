<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Modules\Platform\Http\Middleware\EnsureModuleIsActive;
use App\Modules\Platform\Http\Middleware\EnsureSuiteAccess;
use App\Modules\Platform\Http\Middleware\RequireFeature;
use App\Modules\Platform\Http\Middleware\ResolveApiTenant;
use App\Modules\Platform\Http\Middleware\ResolveSuiteScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel Cloud terminates HTTPS at its load balancer; trust it so URLs are built as https.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'suite.scope' => ResolveSuiteScope::class,
            'api.tenant' => ResolveApiTenant::class,
            'module' => EnsureModuleIsActive::class,
            'access' => EnsureSuiteAccess::class,
            'feature' => RequireFeature::class,
        ]);

        // The tenant and scope must exist before suite models are bound from the route.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveSuiteScope::class);
        // Same for the app and /me: the tenant must be known before models in the URL are looked up.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveApiTenant::class);

        // Signed-in users who open /login go to their own home (admin or portal).
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()?->homeUrl() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
