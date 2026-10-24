<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\ReachableTree;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /suite without a scope: land the user where they belong. One reachable company goes straight
 * in; otherwise the last scope they used, or the whole account.
 */
class SuiteEntryController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $tenant, AccessResolver $resolver, ReachableTree $tree): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->client_id === null, 403);

        $tenant->set($user->client);
        $organizations = $tree->for($resolver->for($user));

        abort_if($organizations->isEmpty(), 403, __('suite.modules.inactive'));

        $companies = $organizations->flatMap->companies;

        if ($companies->count() === 1) {
            return to_route('suite.home', ['scope' => 'c-'.$companies->first()->slug]);
        }

        $last = $request->session()->get('suite.last_scope');
        $segments = ['all', ...$organizations->map(fn ($o) => 'o-'.$o->slug), ...$companies->map(fn ($c) => 'c-'.$c->slug)];

        return to_route('suite.home', ['scope' => in_array($last, $segments, true) ? $last : 'all']);
    }
}
