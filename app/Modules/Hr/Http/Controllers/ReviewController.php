<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Reviews\ReviewFlow;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Review;
use App\Modules\Hr\Models\ReviewCycle;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Performance reviews: the cycles, one cycle's reviews with self and manager scores, and the
 * probations ending soon (decide before the last day; up to 180 days under the 2025 amendments).
 */
class ReviewController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public const DEFAULT_CRITERIA = ['quality', 'productivity', 'attendance', 'teamwork', 'customer'];

    public function index(Request $request, OrgDirectory $directory): Response
    {
        $reach = $this->reach(HrFeature::Reviews, FeatureAction::View);
        $cycles = ReviewCycle::query()->latest('period_end')->get();
        $cycle = $cycles->firstWhere('id', (int) $request->query('cycle')) ?? $cycles->first();
        $canDecide = $this->reach(HrFeature::Reviews, FeatureAction::Approve);

        return $this->suitePage('Hr/Reviews/Index', [
            'cycles' => $cycles->map(fn (ReviewCycle $item): array => [
                'id' => $item->id, 'name' => $item->name, 'status' => $item->status, 'due_on' => $item->due_on->toDateString(),
                'period' => $item->period_start->toDateString().' – '.$item->period_end->toDateString(),
            ])->all(),
            'cycle' => $cycle ? [
                'id' => $cycle->id, 'name' => $cycle->name, 'status' => $cycle->status, 'criteria' => $cycle->criteria, 'due_on' => $cycle->due_on->toDateString(),
                'reviews' => $cycle->reviews()->with('employee')->whereHas('employee', fn ($query) => $query->visibleIn($reach))->get()
                    ->sortBy(fn (Review $review): string => $review->employee->employee_number)
                    ->map(fn (Review $review): array => [
                        ...self::reviewArray($review), 'employee' => $review->employee->localized('name'), 'employee_number' => $review->employee->employee_number,
                        'can_review' => $canDecide->allowsBranch($review->employee->branch_id, $review->employee->company_id),
                    ])->values()->all(),
            ] : null,
            'probations' => Contract::query()->with('employee')->where('status', ContractStatus::Active)->where('probation_days', '>', 0)
                ->whereHas('employee', fn ($query) => $query->visibleIn($reach))->get()
                ->map(fn (Contract $contract): array => ['contract' => $contract, 'ends' => $contract->starts_on->copy()->addDays($contract->probation_days)])
                ->filter(fn (array $row): bool => $row['ends']->between(today(), today()->addDays(30)))->sortBy('ends')
                ->map(fn (array $row): array => ['employee_id' => $row['contract']->employee_id, 'employee' => $row['contract']->employee->localized('name'), 'ends_on' => $row['ends']->toDateString()])
                ->values()->all(),
            'companies' => collect($directory->companies())->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'default_criteria' => array_map(fn (string $key): array => ['key' => $key, 'label_ar' => __("hr.reviews.criteria.{$key}", [], 'ar'), 'label_en' => __("hr.reviews.criteria.{$key}", [], 'en'), 'weight' => 20], self::DEFAULT_CRITERIA),
            'can_launch' => $this->access()->allows(HrFeature::Reviews->key(FeatureAction::Create)),
        ], ['hr']);
    }

    public function store(Request $request, ReviewFlow $flow): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_on' => ['required', 'date'],
            'company_id' => ['nullable', 'integer'],
            'criteria' => ['required', 'array', 'min:1', 'max:10'],
            'criteria.*.key' => ['required', 'string', 'max:30', 'distinct'],
            'criteria.*.label_ar' => ['required', 'string', 'max:80'],
            'criteria.*.label_en' => ['required', 'string', 'max:80'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $cycle = $flow->launch($data, $this->reach(HrFeature::Reviews, FeatureAction::Create), $request->user());
        $this->toast(__('hr.reviews.launched', ['count' => $cycle->reviews()->count()]));

        return redirect()->route('hr.reviews.index', ['scope' => $request->route('scope'), 'cycle' => $cycle->id]);
    }

    public function update(Request $request, Review $review, ReviewFlow $flow): RedirectResponse
    {
        $this->authorizeEmployee($review->employee, HrFeature::Reviews, FeatureAction::Approve);
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'string', 'max:3000'],
        ]);

        $flow->managerAssess($review, $data['scores'], $data['comment'] ?? null, $data['goals'] ?? null, $request->user());
        $this->toast(__('hr.reviews.saved'));

        return back();
    }

    public function close(ReviewCycle $cycle): RedirectResponse
    {
        abort_unless($this->access()->branches(HrFeature::Reviews->key(FeatureAction::Approve))->all, 403);
        $cycle->forceFill(['status' => 'closed'])->save();
        $this->toast(__('hr.reviews.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviewArray(Review $review): array
    {
        return [
            'id' => $review->id, 'employee_id' => $review->employee_id, 'status' => $review->status(),
            'self_scores' => $review->self_scores, 'self_comment' => $review->self_comment,
            'manager_scores' => $review->manager_scores, 'manager_comment' => $review->manager_comment, 'goals' => $review->goals,
            'overall' => $review->overall !== null ? (string) $review->overall : null,
        ];
    }
}
