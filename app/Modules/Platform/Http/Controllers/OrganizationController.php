<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Suite\SaveOrganization;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Requests\SaveOrganizationRequest;
use App\Modules\Platform\Models\Organization;
use Illuminate\Http\RedirectResponse;

class OrganizationController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function store(SaveOrganizationRequest $request, SaveOrganization $saveOrganization): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('create'));

        $saveOrganization->handle(new Organization, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }

    public function update(SaveOrganizationRequest $request, Organization $organization, SaveOrganization $saveOrganization): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('update'));

        $saveOrganization->handle($organization, $request->validated());
        $this->toast(__('suite.structure.saved'));

        return to_route('suite.structure.index');
    }
}
