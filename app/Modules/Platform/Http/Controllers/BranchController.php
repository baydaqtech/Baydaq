<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Suite\DeleteBranch;
use App\Modules\Platform\Actions\Suite\SaveBranch;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\SaveBranchRequest;
use App\Modules\Platform\Http\Resources\BranchResource;
use App\Modules\Platform\Http\Resources\CompanyResource;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class BranchController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function create(Company $company): Response
    {
        $this->authorizeCompany(SuiteFeature::Structure->key('create'), $company);

        return $this->suitePage('Suite/Structure/BranchForm', [
            'company' => new CompanyResource($company),
            'branch' => null,
        ]);
    }

    public function store(SaveBranchRequest $request, Company $company, SaveBranch $saveBranch): RedirectResponse
    {
        $this->authorizeCompany(SuiteFeature::Structure->key('create'), $company);

        $branch = $company->branches()->make();
        $branch->forceFill(['client_id' => $company->client_id]);
        $saveBranch->handle($branch, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }

    public function edit(Branch $branch): Response
    {
        $this->authorizeBranch(SuiteFeature::Structure->key('update'), $branch);

        return $this->suitePage('Suite/Structure/BranchForm', [
            'company' => new CompanyResource($branch->company),
            'branch' => new BranchResource($branch),
        ]);
    }

    public function update(SaveBranchRequest $request, Branch $branch, SaveBranch $saveBranch): RedirectResponse
    {
        $this->authorizeBranch(SuiteFeature::Structure->key('update'), $branch);

        $saveBranch->handle($branch, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }

    public function destroy(Branch $branch, DeleteBranch $deleteBranch): RedirectResponse
    {
        $this->authorizeBranch(SuiteFeature::Structure->key('delete'), $branch);

        $deleteBranch->handle($branch);
        $this->toast(__('suite.structure.deleted'));

        return to_route('suite.structure.index');
    }
}
