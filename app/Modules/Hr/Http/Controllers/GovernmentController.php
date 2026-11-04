<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Payroll\Gosi;
use App\Modules\Hr\Enums\ContractStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\TravelVisa;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Trackers for what lives on government portals without an API: contract documentation on Qiwa,
 * exit/re-entry visas from Muqeem, and who pays GOSI under which scheme.
 */
class GovernmentController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public const QIWA_STATUSES = ['not_started', 'sent', 'documented', 'rejected'];

    public function __construct(private OrgDirectory $directory) {}

    public function qiwa(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $contracts = Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereHas('employee', fn ($query) => $query->visibleIn($this->reach(HrFeature::Contracts, FeatureAction::View))->whereIn('status', EmployeeStatus::seated()))
            ->with('employee:id,company_id,branch_id,employee_number,name_ar,name_en,nationality')
            ->get();
        $companies = collect($this->directory->companies())->keyBy('id');

        return $this->suitePage('Hr/Government/Qiwa', [
            'summary' => $contracts->groupBy(fn (Contract $contract) => $contract->employee->company_id)->map(fn ($group, $companyId): array => [
                'company' => $companies->get($companyId)?->name,
                'total' => $group->count(),
                'documented' => $group->where('qiwa_status', 'documented')->count(),
            ])->values()->all(),
            'contracts' => $contracts
                ->when($status !== '', fn ($all) => $all->where('qiwa_status', $status))
                ->sortBy(fn (Contract $contract): string => array_search($contract->qiwa_status, ['rejected', 'not_started', 'sent', 'documented'], true).$contract->employee->employee_number)
                ->map(fn (Contract $contract): array => [
                    'id' => $contract->id, 'employee_id' => $contract->employee_id, 'employee' => $contract->employee->localized('name'), 'number' => $contract->employee->employee_number,
                    'company' => $companies->get($contract->employee->company_id)?->name, 'starts_on' => $contract->starts_on->toDateString(),
                    'qiwa_status' => $contract->qiwa_status, 'qiwa_reference' => $contract->qiwa_reference, 'qiwa_documented_on' => $contract->qiwa_documented_on?->toDateString(),
                    'can_update' => $this->reach(HrFeature::Contracts, FeatureAction::Update)->allowsBranch($contract->employee->branch_id, $contract->employee->company_id),
                ])->values()->all(),
            'statuses' => self::QIWA_STATUSES,
            'filters' => ['status' => $status],
            'portal' => config('baydaq.gov.portals.qiwa.url'),
        ], ['hr']);
    }

    public function updateQiwa(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeEmployee($contract->employee, HrFeature::Contracts, FeatureAction::Update);
        $data = $request->validate([
            'qiwa_status' => ['required', Rule::in(self::QIWA_STATUSES)],
            'qiwa_reference' => ['nullable', 'string', 'max:40'],
        ]);

        $contract->forceFill([
            ...$data,
            'qiwa_documented_on' => $data['qiwa_status'] === 'documented' ? ($contract->qiwa_documented_on ?? today()) : null,
        ])->save();
        $this->toast(__('hr.gov.qiwa.saved'));

        return back();
    }

    public function muqeem(): Response
    {
        $reach = $this->reach(HrFeature::Documents, FeatureAction::View);
        $nationality = config('baydaq.hr.gosi.nationality');
        $employees = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())
            ->where(fn ($query) => $query->whereNull('nationality')->orWhere('nationality', '!=', $nationality))
            ->orderBy('employee_number')->get();
        $visas = TravelVisa::query()->whereIn('employee_id', $employees->pluck('id'))->latest('issued_on')->get()->groupBy('employee_id');
        $canManage = $this->access()->allows(HrFeature::Documents->key(FeatureAction::Update));

        return $this->suitePage('Hr/Government/Muqeem', [
            'employees' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id, 'name' => $employee->localized('name'), 'number' => $employee->employee_number, 'nationality' => $employee->nationality,
                'visas' => ($visas->get($employee->id) ?? collect())->take(5)->map(fn (TravelVisa $visa): array => $this->visaArray($visa))->values()->all(),
            ])->all(),
            'alerts' => $visas->flatten()->filter(fn (TravelVisa $visa): bool => $visa->alert() !== null)
                ->map(fn (TravelVisa $visa): array => [...$this->visaArray($visa), 'employee' => $employees->firstWhere('id', $visa->employee_id)?->localized('name')])->values()->all(),
            'kinds' => TravelVisa::KINDS,
            'can_manage' => $canManage,
            'portal' => config('baydaq.gov.portals.muqeem.url'),
        ], ['hr']);
    }

    public function storeVisa(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Update);
        $data = $request->validate([
            'kind' => ['required', Rule::in(TravelVisa::KINDS)],
            'number' => ['nullable', 'string', 'max:30'],
            'issued_on' => ['required', 'date'],
            'expires_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'duration_days' => ['nullable', 'required_unless:kind,final_exit', 'integer', 'min:1', 'max:730'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $visa = new TravelVisa($data);
        $visa->employee_id = $employee->id;
        $visa->created_by = $request->user()->id;
        $visa->save();
        $this->toast(__('hr.gov.muqeem.saved'));

        return back();
    }

    /**
     * Moves a visa along: departed, returned, cancelled, or not returned (the employee stayed out).
     */
    public function updateVisa(Request $request, TravelVisa $visa): RedirectResponse
    {
        $this->authorizeEmployee($visa->employee, HrFeature::Documents, FeatureAction::Update);
        $data = $request->validate([
            'action' => ['required', 'in:depart,return,cancel,not_returned'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $date = $data['date'] ?? today()->toDateString();
        $allowed = match ($data['action']) {
            'depart', 'cancel' => $visa->status === 'issued',
            'return' => $visa->status === 'abroad' && $visa->kind !== 'final_exit',
            'not_returned' => $visa->status === 'abroad',
        };
        abort_unless($allowed, 422);

        $visa->forceFill(match ($data['action']) {
            'depart' => ['status' => 'abroad', 'departed_on' => $date],
            'return' => ['status' => 'returned', 'returned_on' => $date],
            'cancel' => ['status' => 'cancelled'],
            'not_returned' => ['status' => 'not_returned'],
        })->save();
        $this->toast(__('hr.gov.muqeem.saved'));

        return back();
    }

    /**
     * Who pays GOSI under which scheme, and what each side pays this month.
     */
    public function gosi(Gosi $gosi): Response
    {
        $employees = $this->employees('view_salaries')->whereIn('status', EmployeeStatus::seated())
            ->with('activeContract.allowances.type')->orderBy('employee_number')->get();
        $companies = collect($this->directory->companies())->keyBy('id');

        $rows = $employees->map(function (Employee $employee) use ($gosi, $companies): array {
            $result = $gosi->handle($employee, $employee->activeContract);

            return [
                'id' => $employee->id, 'name' => $employee->localized('name'), 'number' => $employee->employee_number,
                'company' => $companies->get($employee->company_id)?->name, 'saudi' => Gosi::isSaudi($employee),
                'scheme' => $result['scheme'], 'pension_rate' => $result['pension_rate'], 'registered_on' => $employee->gosi_registered_on?->toDateString(),
                'wage' => (string) $result['wage'], 'employee_share' => (string) $result['employee'], 'employer_share' => (string) $result['employer'],
            ];
        });

        return $this->suitePage('Hr/Government/Gosi', [
            'rows' => $rows->all(),
            'totals' => [
                'employee' => (string) $rows->reduce(fn ($sum, $row) => bcadd($sum, $row['employee_share'], 2), '0.00'),
                'employer' => (string) $rows->reduce(fn ($sum, $row) => bcadd($sum, $row['employer_share'], 2), '0.00'),
            ],
            'new_rate' => Gosi::newPensionRate(today()),
            'legacy_rate' => (string) config('baydaq.hr.gosi.pension_legacy'),
            'new_system_from' => config('baydaq.hr.gosi.new_system_from'),
            'unknown' => $rows->filter(fn ($row) => $row['saudi'] && $row['registered_on'] === null)->count(),
            'portal' => config('baydaq.gov.portals.gosi.url'),
        ], ['hr']);
    }

    /**
     * @return array<string, mixed>
     */
    private function visaArray(TravelVisa $visa): array
    {
        return [
            'id' => $visa->id, 'employee_id' => $visa->employee_id, 'kind' => $visa->kind, 'number' => $visa->number, 'status' => $visa->status,
            'issued_on' => $visa->issued_on->toDateString(), 'expires_on' => $visa->expires_on->toDateString(), 'duration_days' => $visa->duration_days,
            'departed_on' => $visa->departed_on?->toDateString(), 'returned_on' => $visa->returned_on?->toDateString(),
            'return_by' => $visa->returnBy()?->toDateString(), 'alert' => $visa->alert(),
        ];
    }
}
