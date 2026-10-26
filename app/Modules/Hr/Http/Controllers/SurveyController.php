<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\Audience;
use App\Modules\Hr\Actions\Engagement\SurveyFlow;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Survey;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Staff surveys and their results (eNPS, ratings, choices, comments).
 */
class SurveyController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Audience $audience, SurveyFlow $flow): Response
    {
        $reach = $this->reach(HrFeature::Surveys, FeatureAction::View);
        $visible = Employee::query()->visibleIn($reach);

        return $this->suitePage('Hr/Surveys/Index', [
            'surveys' => Survey::query()
                ->when(! $reach->all, fn ($query) => $query->forPlaces((clone $visible)->distinct()->pluck('company_id')->all(), (clone $visible)->distinct()->pluck('branch_id')->all()))
                ->latest()->limit(50)->get()->map(fn (Survey $survey): array => [
                    ...self::surveyArray($survey), 'audience' => $audience->label($survey->company_id, $survey->branch_id),
                    'results' => $flow->results($survey),
                    'can_close' => $survey->status === 'open' && $this->canAddress($survey),
                ])->all(),
            'audiences' => $audience->options($this->reach(HrFeature::Surveys, FeatureAction::Create)),
            'types' => SurveyFlow::TYPES,
            'min_anonymous' => SurveyFlow::MIN_ANONYMOUS,
        ], ['hr']);
    }

    public function store(Request $request, Audience $audience): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'anonymous' => ['boolean'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:today'],
            'audience' => ['required', 'string', 'max:30'],
            'questions' => ['required', 'array', 'min:1', 'max:30'],
            'questions.*.type' => ['required', Rule::in(SurveyFlow::TYPES)],
            'questions.*.label' => ['required', 'string', 'max:300'],
            'questions.*.options' => ['nullable', 'array', 'max:12'],
            'questions.*.options.*' => ['nullable', 'string', 'max:100'],
        ]);
        [$companyId, $branchId] = Audience::parse($data['audience']);
        $target = $audience->resolve($companyId, $branchId, $this->reach(HrFeature::Surveys, FeatureAction::Create));

        $survey = new Survey(['title' => $data['title'], 'description' => $data['description'] ?? null, 'anonymous' => (bool) ($data['anonymous'] ?? true), 'closes_on' => $data['closes_on'] ?? null, 'questions' => SurveyFlow::normalizeQuestions($data['questions'])]);
        $survey->forceFill(['client_id' => $request->user()->client_id, ...$target, 'status' => 'open', 'created_by' => $request->user()->id])->save();
        $this->toast(__('hr.surveys.saved'));

        return back();
    }

    public function close(Survey $survey): RedirectResponse
    {
        abort_unless($this->canAddress($survey), 403);
        $survey->forceFill(['status' => 'closed'])->save();
        $this->toast(__('hr.surveys.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function surveyArray(Survey $survey): array
    {
        return [
            'id' => $survey->id, 'title' => $survey->title, 'description' => $survey->description, 'questions' => $survey->questions,
            'anonymous' => $survey->anonymous, 'status' => $survey->status, 'closes_on' => $survey->closes_on?->toDateString(),
        ];
    }

    /**
     * Open surveys for this employee that they haven't answered yet.
     *
     * @return list<array<string, mixed>>
     */
    public static function openFor(Employee $employee): array
    {
        $flow = app(SurveyFlow::class);

        return Survey::query()->forEmployee($employee)->where('status', 'open')
            ->where(fn ($query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', today()))
            ->latest()->limit(10)->get()
            ->reject(fn (Survey $survey): bool => $flow->hasAnswered($survey, $employee))
            ->map(fn (Survey $survey): array => self::surveyArray($survey))->values()->all();
    }

    private function canAddress(Survey $survey): bool
    {
        $reach = $this->reach(HrFeature::Surveys, FeatureAction::Create);

        return match (true) {
            $survey->branch_id !== null => $reach->allowsBranch($survey->branch_id, (int) $survey->company_id),
            $survey->company_id !== null => $reach->allowsWholeCompany($survey->company_id),
            default => $reach->all,
        };
    }
}
