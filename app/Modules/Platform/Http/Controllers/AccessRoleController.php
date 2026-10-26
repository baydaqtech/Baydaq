<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Actions\Access\SaveAccessRole;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\SaveAccessRoleRequest;
use App\Modules\Platform\Http\Resources\AccessRoleResource;
use App\Modules\Platform\Models\Access\AccessRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class AccessRoleController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(): Response
    {
        $roles = AccessRole::query()->with('permissions')->withCount('assignments')->orderByDesc('is_locked')->orderBy('name_en')->get();

        return $this->suitePage('Suite/Access/Roles/Index', [
            'roles' => AccessRoleResource::collection($roles),
        ]);
    }

    public function create(FeatureRegistry $registry): Response
    {
        return $this->suitePage('Suite/Access/Roles/Form', [
            'role' => null,
            'matrix' => $registry->matrix(),
            'grantable' => $this->access()->keys(),
        ]);
    }

    public function store(SaveAccessRoleRequest $request, SaveAccessRole $saveAccessRole): RedirectResponse
    {
        $saveAccessRole->handle(new AccessRole, $request->safe()->only(['name_ar', 'name_en']), $request->validated('permissions'), $request->user());
        $this->toast(__('suite.access.role_saved'));

        return to_route('suite.access.roles.index');
    }

    public function edit(AccessRole $role, FeatureRegistry $registry): Response
    {
        abort_if($role->is_locked, 403);

        return $this->suitePage('Suite/Access/Roles/Form', [
            'role' => new AccessRoleResource($role->load('permissions')),
            'matrix' => $registry->matrix(),
            'grantable' => $this->access()->keys(),
        ]);
    }

    public function update(SaveAccessRoleRequest $request, AccessRole $role, SaveAccessRole $saveAccessRole): RedirectResponse
    {
        $saveAccessRole->handle($role, $request->safe()->only(['name_ar', 'name_en']), $request->validated('permissions'), $request->user());
        $this->toast(__('suite.access.role_saved'));

        return to_route('suite.access.roles.index');
    }
}
