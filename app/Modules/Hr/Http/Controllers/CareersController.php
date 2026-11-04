<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Recruitment\HiringFlow;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Platform\Ports\Branding;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page behind an opening's careers link: what the job is, and an application form with
 * a CV upload. Works while the account's package includes hiring.
 */
class CareersController extends Controller
{
    public function __construct(private CurrentTenant $tenant) {}

    public function show(string $token): Response
    {
        $opening = $this->opening($token);

        return $this->tenant->runFor($opening->client_id, fn (): Response => Inertia::render('Hr/Careers', [
            'token' => $token,
            'opening' => [
                'title' => $opening->localized('title'), 'description' => $opening->description, 'accepting' => $opening->isAccepting(),
                'company' => app(OrgDirectory::class)->company($opening->company_id)?->name, 'branch' => app(OrgDirectory::class)->branch($opening->branch_id)?->name,
                'closes_on' => $opening->closes_on?->toDateString(),
            ],
            'theme' => app(Branding::class)->theme(),
            'copy' => ['hr' => ['careers' => trans('hr.careers')]],
        ]));
    }

    public function apply(Request $request, string $token, HiringFlow $flow): RedirectResponse
    {
        $opening = $this->opening($token);
        $data = RecruitmentController::validateApplication($request);
        $request->validate(['consent' => ['accepted']]);

        $this->tenant->runFor($opening->client_id, fn () => $flow->apply($opening, $data, $request->file('cv'), 'careers'));
        Inertia::flash('applied', true);

        return back();
    }

    private function opening(string $token): JobOpening
    {
        $opening = JobOpening::withoutGlobalScopes()->where('token', $token)->where('status', '!=', 'draft')->firstOrFail();
        abort_unless($this->tenant->runFor($opening->client_id, fn (): bool => app(Entitlements::class)->hasFeature('hr.recruitment')), 404);

        return $opening;
    }
}
