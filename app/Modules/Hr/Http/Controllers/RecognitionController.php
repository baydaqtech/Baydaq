<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\RecognitionFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Recognition;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The recognition wall: thanks and awards, the month's most recognised, and giving a reward.
 */
class RecognitionController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(): Response
    {
        $reach = $this->reach(HrFeature::Recognition, FeatureAction::View);
        $canCreate = $this->reach(HrFeature::Recognition, FeatureAction::Create);
        $ids = Employee::query()->visibleIn($reach)->select('id');

        return $this->suitePage('Hr/Recognition/Index', [
            ...self::wall(Recognition::query()->whereIn('employee_id', $ids)),
            'employees' => Employee::query()->visibleIn($canCreate)->whereIn('status', EmployeeStatus::seated())->orderBy('employee_number')->get()
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name').' · '.$employee->employee_number])->all(),
            'badges' => RecognitionFlow::BADGES,
        ], ['hr']);
    }

    public function store(Request $request, RecognitionFlow $flow): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'badge' => ['required', Rule::in(RecognitionFlow::BADGES)],
            'message' => ['required', 'string', 'max:500'],
            'reward' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $this->authorizeEmployee($employee, HrFeature::Recognition, FeatureAction::Create);

        // A money reward is a bonus, so it needs the right to create bonuses for this employee.
        if ((float) ($data['reward'] ?? 0) > 0) {
            $this->authorizeEmployee($employee, HrFeature::Pay, FeatureAction::Create);
        }

        $flow->give($employee, $data['badge'], $data['message'], null, $request->user(), isset($data['reward']) ? (string) $data['reward'] : null);
        $this->toast(__('hr.recognition.sent'));

        return back();
    }

    /**
     * Latest recognitions and the month's most recognised people.
     *
     * @param  Builder<Recognition>  $query
     * @return array{items: list<array<string, mixed>>, top: list<array{name: string, count: int}>}
     */
    public static function wall(Builder $query): array
    {
        $items = (clone $query)->with('employee')->latest()->limit(60)->get();
        $givers = Employee::query()->whereKey($items->pluck('giver_employee_id')->filter()->unique())->get()->keyBy('id');
        $top = (clone $query)->where('created_at', '>=', now()->startOfMonth())->selectRaw('employee_id, count(*) as total')->groupBy('employee_id')->orderByDesc('total')->limit(5)->pluck('total', 'employee_id');
        $names = Employee::query()->whereKey($top->keys())->get()->keyBy('id');

        return [
            'items' => $items->map(fn (Recognition $recognition): array => [
                'id' => $recognition->id, 'employee' => $recognition->employee->localized('name'), 'badge' => $recognition->badge, 'message' => $recognition->message,
                'from' => $recognition->giver_employee_id ? $givers->get($recognition->giver_employee_id)?->localized('name') : null,
                'rewarded' => $recognition->bonus_id !== null, 'at' => $recognition->created_at->toIso8601String(),
            ])->all(),
            'top' => $top->map(fn ($total, $employeeId): array => ['name' => (string) $names->get($employeeId)?->localized('name'), 'count' => (int) $total])->values()->all(),
        ];
    }
}
