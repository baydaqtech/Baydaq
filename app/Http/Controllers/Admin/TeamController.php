<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateTeamMember;
use App\Actions\Users\SendAccountInvite;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTeamMemberRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TeamController extends Controller
{
    use RendersAdminPages;

    public function index(): Response
    {
        $members = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [Role::SystemAdmin->value, Role::Admin->value]))
            ->with('permissions', 'roles')
            ->orderBy('name')
            ->get();

        return $this->adminPage('Admin/Team/Index', ['members' => UserResource::collection($members)]);
    }

    public function create(): Response
    {
        return $this->adminPage('Admin/Team/Form', ['member' => null, 'permissions' => Permission::values()]);
    }

    public function store(SaveTeamMemberRequest $request, CreateTeamMember $createTeamMember): RedirectResponse
    {
        $createTeamMember->handle($request->safe()->only(['name', 'email', 'locale']), $request->validated('permissions', []));
        $this->toast(__('admin.team.created'));

        return to_route('admin.team.index');
    }

    public function edit(User $member): Response
    {
        $this->ensureEditable($member);

        return $this->adminPage('Admin/Team/Form', [
            'member' => new UserResource($member->load('permissions')),
            'permissions' => Permission::values(),
        ]);
    }

    public function update(SaveTeamMemberRequest $request, User $member): RedirectResponse
    {
        $this->ensureEditable($member);

        $member->update($request->safe()->only(['name', 'locale']));
        $member->syncPermissions($request->validated('permissions', []));
        $this->toast(__('admin.common.saved'));

        return to_route('admin.team.index');
    }

    public function resendInvite(User $member, SendAccountInvite $sendAccountInvite): RedirectResponse
    {
        abort_unless($member->isAdmin(), 404);

        $sendAccountInvite->handle($member);
        $this->toast(__('admin.team.resent'));

        return back();
    }

    /**
     * Only team members are edited here, and the system owner's access is fixed.
     */
    private function ensureEditable(User $member): void
    {
        abort_unless($member->hasRole(Role::Admin) && ! $member->isSystemAdmin(), 404);
    }
}
