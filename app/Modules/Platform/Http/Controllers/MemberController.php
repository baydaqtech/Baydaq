<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Access\InviteSuiteMember;
use App\Modules\Platform\Actions\Access\RevokeAccess;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\AssignAccessRequest;
use App\Modules\Platform\Http\Requests\InviteMemberRequest;
use App\Modules\Platform\Http\Resources\AccessRoleResource;
use App\Modules\Platform\Http\Resources\MemberResource;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * People of the client and the roles they hold. A member is any login of the client that is
 * not a Baydaq client account; owners appear here too, with their locked owner role.
 */
class MemberController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(Request $request, CurrentTenant $tenant): Response
    {
        $filters = $request->only(['search']);

        $members = User::query()
            ->where('client_id', $tenant->id())
            ->with(['accessAssignments.role', 'accessAssignments.organization', 'accessAssignments.company', 'accessAssignments.branch'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereAny(['name', 'email'], 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return $this->suitePage('Suite/Access/Members/Index', [
            'members' => MemberResource::collection($members),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, CurrentTenant $tenant): Response
    {
        return $this->suitePage('Suite/Access/Members/Form', [
            'users' => User::query()->where('client_id', $tenant->id())->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => "{$user->name} — {$user->email}"])->all(),
            'roles' => AccessRoleResource::collection(AccessRole::query()->where('is_locked', false)->orderBy('name_en')->get()),
            'levels' => ScopeLevel::options(),
            'user_id' => $request->integer('user_id') ?: null,
        ]);
    }

    public function store(AssignAccessRequest $request, AssignAccess $assignAccess, CurrentTenant $tenant): RedirectResponse
    {
        $level = ScopeLevel::from($request->validated('level'));
        $node = match ($level) {
            ScopeLevel::Client => null,
            ScopeLevel::Organization => Organization::query()->findOrFail($request->validated('node_id')),
            ScopeLevel::Company => Company::query()->findOrFail($request->validated('node_id')),
            ScopeLevel::Branch => Branch::query()->findOrFail($request->validated('node_id')),
        };

        $assignAccess->handle(
            User::query()->where('client_id', $tenant->id())->findOrFail($request->validated('user_id')),
            AccessRole::query()->findOrFail($request->validated('access_role_id')),
            $level,
            $node,
            $request->user(),
        );
        $this->toast(__('suite.access.granted'));

        return to_route('suite.access.members.index');
    }

    public function invite(InviteMemberRequest $request, InviteSuiteMember $inviteSuiteMember, CurrentTenant $tenant): RedirectResponse
    {
        $user = $inviteSuiteMember->handle($tenant->client(), $request->validated());
        $this->toast(__('suite.access.invited'));

        return to_route('suite.access.members.create', ['user_id' => $user->id]);
    }

    public function destroy(AccessAssignment $assignment, RevokeAccess $revokeAccess, Request $request): RedirectResponse
    {
        $revokeAccess->handle($assignment, $request->user());
        $this->toast(__('suite.access.revoked'));

        return back();
    }
}
