<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Access\AccessMap;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Access\ReachableTree;
use App\Modules\Platform\Access\SuiteScope;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Models\ScopeShortcut;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant from the signed-in user, reads the {scope} segment (all | o-slug | c-slug)
 * and the ?branches filter, and shares the trimmed tree with the page. Runs before route
 * model binding so suite models resolve inside the tenant.
 */
class ResolveSuiteScope
{
    public function __construct(
        private CurrentTenant $tenant,
        private AccessResolver $resolver,
        private ReachableTree $tree,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->client_id === null) {
            abort(403);
        }

        $this->tenant->set($user->client);
        $access = $this->resolver->for($user);
        $organizations = $this->tree->for($access);

        if ($organizations->isEmpty()) {
            abort(403, __('suite.modules.inactive'));
        }

        $segment = (string) $request->route('scope');
        $scope = $this->resolveScope($segment, $organizations, $access, $request);

        URL::defaults(['scope' => $segment]);
        $request->route()->forgetParameter('scope');
        $request->session()->put('suite.last_scope', $segment);

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            ScopeShortcut::visit($user->id, $scope->segment(), $scope->selectedBranchIds);
        }
        app()->instance(SuiteScope::class, $scope);

        Inertia::share('suite', fn (): array => $this->props($scope, $organizations, $access, $user->id));
        Inertia::encryptHistory();

        return $next($request);
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     */
    private function resolveScope(string $segment, $organizations, AccessMap $access, Request $request): SuiteScope
    {
        $organization = null;
        $company = null;

        if (str_starts_with($segment, 'o-')) {
            $organization = $organizations->firstWhere('slug', substr($segment, 2)) ?? abort(404);
            $level = 'organization';
            $node = BranchSet::of($organization->companies->pluck('id')->all());
            $branches = $organization->companies->flatMap->branches;
        } elseif (str_starts_with($segment, 'c-')) {
            $company = $organizations->flatMap->companies->firstWhere('slug', substr($segment, 2)) ?? abort(404);
            $organization = $organizations->firstWhere('id', $company->organization_id);
            $level = 'company';
            $node = BranchSet::of([$company->id]);
            $branches = $company->branches;
        } else {
            $level = 'all';
            $node = BranchSet::all();
            $branches = $organizations->flatMap->companies->flatMap->branches;
        }

        $branchCompanies = $branches->pluck('company_id', 'id')->map(fn ($id): int => (int) $id)->all();
        $selected = [];

        if ($request->filled('branches')) {
            $requested = array_map('intval', explode(',', (string) $request->query('branches')));
            $selected = array_values(array_filter($requested, fn (int $id): bool => isset($branchCompanies[$id])));

            if ($selected === []) {
                abort(403, __('suite.scope.no_branches'));
            }
        }

        return new SuiteScope($level, $organization, $company, $selected, $branchCompanies, $node);
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @return array<string, mixed>
     */
    private function props(SuiteScope $scope, $organizations, AccessMap $access, int $userId): array
    {
        return [
            'scope' => [
                'level' => $scope->level,
                'segment' => $scope->segment(),
                'organization' => $scope->organization ? ['slug' => $scope->organization->slug, 'name' => $scope->organization->localized('name')] : null,
                'company' => $scope->company ? ['slug' => $scope->company->slug, 'name' => $scope->company->localized('name')] : null,
                'branches' => $scope->selectedBranchIds,
            ],
            'tree' => $organizations->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'slug' => $organization->slug,
                'name' => $organization->localized('name'),
                'companies' => $organization->companies->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'slug' => $company->slug,
                    'name' => $company->localized('name'),
                    'branches' => $company->branches->map(fn (Branch $branch): array => [
                        'id' => $branch->id,
                        'code' => $branch->code,
                        'name' => $branch->localized('name'),
                        'is_head_office' => $branch->is_head_office,
                    ])->all(),
                ])->all(),
            ])->all(),
            'shortcuts' => $this->shortcuts($organizations, $userId),
            'ai' => app(Intelligence::class)->isEnabled() && app(Entitlements::class)->hasFeature('suite.ai'),
            'features' => app(Entitlements::class)->features(),
            'can' => $access->keys(),
            'is_owner' => $access->isOwner(),
            'modules' => Subscription::query()->whereIn('status', ['active', 'lapsed'])->pluck('module')->map(fn ($module) => $module->value)->values()->all(),
        ];
    }

    /**
     * Pinned scopes first, then the latest visited, each with a readable label. Shortcuts that
     * point somewhere the user can no longer reach are left out.
     *
     * @param  Collection<int, Organization>  $organizations
     * @return list<array{id: int, name: ?string, label: string, segment: string, branches: list<int>, pinned: bool}>
     */
    private function shortcuts($organizations, int $userId): array
    {
        $companies = $organizations->flatMap->companies;
        $branchNames = $companies->flatMap->branches->mapWithKeys(fn (Branch $branch): array => [$branch->id => $branch->localized('name')]);
        $nodes = collect(['all' => __('suite.scope.all')])
            ->merge($organizations->mapWithKeys(fn (Organization $organization): array => ['o-'.$organization->slug => $organization->localized('name')]))
            ->merge($companies->mapWithKeys(fn (Company $company): array => ['c-'.$company->slug => $company->localized('name')]));

        return ScopeShortcut::query()->where('user_id', $userId)
            ->orderByDesc('pinned')->orderByDesc('last_used_at')->limit(30)->get()
            ->filter(fn (ScopeShortcut $shortcut): bool => $nodes->has($shortcut->segment) && collect($shortcut->branchIds())->every(fn (int $id): bool => $branchNames->has($id)))
            ->map(fn (ScopeShortcut $shortcut): array => [
                'id' => $shortcut->id,
                'name' => $shortcut->name,
                'label' => collect([$nodes[$shortcut->segment], ...collect($shortcut->branchIds())->map(fn (int $id) => $branchNames[$id])])->implode(' › '),
                'segment' => $shortcut->segment,
                'branches' => $shortcut->branchIds(),
                'pinned' => $shortcut->pinned,
            ])->values()->all();
    }
}
