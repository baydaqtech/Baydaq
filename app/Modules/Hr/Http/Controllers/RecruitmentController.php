<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Recruitment\HiringFlow;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hiring: openings with their public careers link, and each opening's candidates as a pipeline.
 */
class RecruitmentController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory) {}

    public function index(Request $request): Response
    {
        $reach = $this->reach(HrFeature::Recruitment, FeatureAction::View);
        $openings = $reach->apply(JobOpening::query())->withCount(['candidates', 'candidates as hired_count' => fn ($query) => $query->where('stage', 'hired')])
            ->orderByRaw("status = 'open' desc")->latest()->get();
        $selected = $openings->firstWhere('id', (int) $request->query('opening')) ?? $openings->firstWhere('status', 'open') ?? $openings->first();

        return $this->suitePage('Hr/Recruitment/Index', [
            'openings' => $openings->map(fn (JobOpening $opening): array => [
                'id' => $opening->id, 'title' => $opening->localized('title'), 'title_ar' => $opening->title_ar, 'title_en' => $opening->title_en,
                'description' => $opening->description, 'positions' => $opening->positions, 'status' => $opening->status, 'closes_on' => $opening->closes_on?->toDateString(),
                'job_id' => $opening->job_id, 'branch' => $this->directory->branch($opening->branch_id)?->name, 'branch_id' => $opening->branch_id,
                'candidates' => $opening->candidates_count, 'hired' => $opening->hired_count, 'link' => route('hr.careers.show', $opening->token),
            ])->all(),
            'selected' => $selected?->id,
            'candidates' => $selected ? $selected->candidates()->latest()->get()->map(fn (Candidate $candidate): array => [
                ...$candidate->only(['id', 'name', 'phone', 'email', 'nationality', 'stage', 'rating', 'notes', 'source', 'employee_id']),
                'interview_at' => $candidate->interview_at?->format('Y-m-d\TH:i'), 'offer_salary' => $candidate->offer_salary !== null ? (string) $candidate->offer_salary : null,
                'has_cv' => $candidate->cv_path !== null, 'applied_at' => $candidate->created_at->toIso8601String(),
            ])->all() : [],
            'stages' => Candidate::STAGES,
            'branches' => $this->branchOptions(FeatureAction::Create),
            'jobs' => Job::query()->get()->map(fn (Job $job): array => ['value' => $job->id, 'label' => $job->localized('name')])->all(),
            'can_manage' => $this->access()->allows(HrFeature::Recruitment->key(FeatureAction::Create)),
            'can_hire' => $selected !== null && $this->reach(HrFeature::Recruitment, FeatureAction::Approve)->allowsBranch($selected->branch_id, $selected->company_id),
        ], ['hr']);
    }

    public function storeOpening(Request $request, HiringFlow $flow): RedirectResponse
    {
        $data = $this->validateOpening($request);
        $branch = $this->directory->branch((int) $request->validate(['branch_id' => ['required', 'integer']])['branch_id']);
        abort_unless($branch && $this->reach(HrFeature::Recruitment, FeatureAction::Create)->allowsBranch($branch->id, $branch->companyId), 403);

        $opening = $flow->open(new JobOpening, $data, $branch->companyId, $branch->id, $request->user());
        $this->toast(__('hr.recruitment.saved'));

        return redirect()->route('hr.recruitment.index', ['scope' => $request->route('scope'), 'opening' => $opening->id]);
    }

    public function updateOpening(Request $request, JobOpening $opening, HiringFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Recruitment, FeatureAction::Update)->allowsBranch($opening->branch_id, $opening->company_id), 403);
        $flow->open($opening, $this->validateOpening($request), $opening->company_id, $opening->branch_id, $request->user());
        $this->toast(__('hr.recruitment.saved'));

        return back();
    }

    public function storeCandidate(Request $request, JobOpening $opening, HiringFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Recruitment, FeatureAction::Create)->allowsBranch($opening->branch_id, $opening->company_id), 403);
        $flow->apply($opening, self::validateApplication($request), $request->file('cv'), $request->input('source') === 'referral' ? 'referral' : 'manual');
        $this->toast(__('hr.recruitment.saved'));

        return back();
    }

    public function updateCandidate(Request $request, Candidate $candidate, HiringFlow $flow): RedirectResponse
    {
        $opening = $candidate->opening;
        abort_unless($this->reach(HrFeature::Recruitment, FeatureAction::Update)->allowsBranch($opening->branch_id, $opening->company_id), 403);
        $flow->update($candidate, $request->validate([
            'stage' => ['sometimes', Rule::in(Candidate::STAGES)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'interview_at' => ['nullable', 'date'],
            'offer_salary' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]), $request->user());

        return back();
    }

    public function hire(Request $request, Candidate $candidate, HiringFlow $flow): RedirectResponse
    {
        $opening = $candidate->opening;
        abort_unless($this->reach(HrFeature::Recruitment, FeatureAction::Approve)->allowsBranch($opening->branch_id, $opening->company_id), 403);
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:30'],
            'branch_id' => ['required', 'integer'],
            'hired_on' => ['required', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'probation_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'national_id' => ['nullable', 'string', 'max:30'],
        ]);
        $branch = $this->directory->branch((int) $data['branch_id']);
        abort_unless($branch && $branch->companyId === $opening->company_id, 422);

        $employee = $flow->hire($candidate, $data, $request->user());
        $this->toast(__('hr.recruitment.hired'));

        return redirect()->route('hr.employees.show', ['scope' => $request->route('scope'), 'employee' => $employee->id]);
    }

    public function cv(Candidate $candidate): StreamedResponse
    {
        $opening = $candidate->opening;
        abort_unless($candidate->cv_path && $this->reach(HrFeature::Recruitment, FeatureAction::View)->allowsBranch($opening->branch_id, $opening->company_id), 404);

        return Storage::disk(config('baydaq.hr.disk'))->download($candidate->cv_path, 'cv-'.$candidate->id.'.'.pathinfo($candidate->cv_path, PATHINFO_EXTENSION));
    }

    /**
     * @return array<string, mixed>
     */
    public static function validateApplication(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ]);
        unset($data['cv']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOpening(Request $request): array
    {
        return $request->validate([
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'job_id' => ['nullable', 'integer'],
            'positions' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['required', 'in:draft,open,closed'],
            'closes_on' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return list<array{value: int, label: string, company_id: int}>
     */
    private function branchOptions(FeatureAction $action): array
    {
        $reach = $this->reach(HrFeature::Recruitment, $action);

        return collect($this->directory->companies())->flatMap(fn ($company) => collect($this->directory->branches($company->id))
            ->filter(fn (BranchRef $branch): bool => $reach->allowsBranch($branch->id, $branch->companyId))
            ->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $company->name.' · '.$branch->name, 'company_id' => $branch->companyId]))->values()->all();
    }
}
