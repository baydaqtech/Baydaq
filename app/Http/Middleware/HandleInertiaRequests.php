<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Paths served by the platform shell (Tailwind + shadcn) instead of the landing shell.
     *
     * @var list<string>
     */
    private const PLATFORM_PATHS = [
        'admin', 'admin/*',
        'app', 'app/*',
        'login', 'logout',
        'forgot-password', 'reset-password', 'reset-password/*',
        'account/*', 'password/*',
    ];

    /**
     * Public pages use the landing look; sign-in, the client portal and the admin use the platform look.
     */
    public function rootView(Request $request): string
    {
        return $request->is(...self::PLATFORM_PATHS) ? 'platform' : 'app';
    }

    /**
     * Determines the current asset version.
     *
     * The root view and the language are part of the version, so an Inertia visit that
     * crosses from the landing shell to the platform shell (or back), or that lands in
     * another language (e.g. a user's saved language after signing in), becomes a full
     * page load with the right <html lang/dir>.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request).':'.$this->rootView($request).':'.app()->getLocale();
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => config("baydaq.locales.{$locale}", 'rtl'),
            'localeUrls' => collect(config('baydaq.locales'))
                ->keys()
                ->mapWithKeys(fn (string $code): array => [$code => $request->fullUrlWithQuery(['lang' => $code])])
                ->all(),
            'i18n' => Inertia::once(fn (): array => [
                'common' => trans('common'),
                'enums' => trans('enums'),
            ])->as("i18n.{$locale}"),
            'auth' => [
                'user' => fn (): ?array => $this->currentUser($request),
            ],
            'status' => fn (): ?string => $request->session()->get('status'),
        ];
    }

    /**
     * The signed-in user as the UI needs it: who they are and what they may do.
     *
     * @return array{id: int, name: string, email: string, locale: string, roles: list<string>, permissions: list<string>}|null
     */
    private function currentUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->permissionNames(),
        ];
    }
}
