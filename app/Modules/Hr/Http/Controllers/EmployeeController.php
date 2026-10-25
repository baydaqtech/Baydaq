<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Actions\Employees\DualControl;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Payroll\SettleFinalAccount;
use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\Gender;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\IdType;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Http\Requests\SaveEmployeeRequest;
use App\Modules\Hr\Http\Resources\CatalogResource;
use App\Modules\Hr\Http\Resources\ContractResource;
use App\Modules\Hr\Http\Resources\EmployeeDocumentResource;
use App\Modules\Hr\Http\Resources\EmployeeResource;
use App\Modules\Hr\Http\Resources\MovementResource;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\FinalSettlement;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Letter;
use App\Modules\Hr\Models\Section;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class EmployeeController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory) {}

    public function index(Request $request, Entitlements $entitlements): Response
    {
        $filters = $request->only(['search', 'status', 'section_id']);

        $employees = $this->employees()
            ->with(['section', 'job'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereAny(['name_ar', 'name_en', 'employee_number', 'email'], 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['section_id'] ?? null, fn (Builder $query, string $id) => $query->where('section_id', $id))
            ->orderBy('name_en')
            ->paginate(20)
            ->withQueryString();

        $branchNames = collect($this->directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name])->all();

        return $this->suitePage('Hr/Employees/Index', [
            'employees' => EmployeeResource::collection($employees),
            'branches' => $branchNames,
            'filters' => $filters,
            'statuses' => EmployeeStatus::options(),
            'sections' => Section::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Section $section): array => ['value' => $section->id, 'label' => $section->localized('name')])->all(),
            'seats' => ['used' => Employee::query()->whereIn('status', EmployeeStatus::seated())->count(), 'total' => $entitlements->quantity(Module::Hr)],
        ], ['hr']);
    }

    public function create(): Response
    {
        return $this->suitePage('Hr/Employees/Form', ['employee' => null, ...$this->formOptions(FeatureAction::Create)], ['hr']);
    }

    public function store(SaveEmployeeRequest $request, SaveEmployee $saveEmployee): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($this->reach(HrFeature::Employees, FeatureAction::Create)->allowsBranch((int) $data['branch_id'], (int) $data['company_id']), 403);

        $employee = $saveEmployee->handle(new Employee, $data, $request->user());
        $this->toast(__('hr.employees.created'));

        return to_route('hr.employees.show', $employee);
    }

    public function show(Employee $employee): Response
    {
        $this->authorizeEmployee($employee, HrFeature::Employees, FeatureAction::View);
        $employee->load(['section', 'job', 'manager', 'contracts.allowances.type', 'documents.type', 'documents.files', 'movements']);

        $can = $this->abilities($employee);
        $branchNames = collect($this->directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name])->all();
        $company = $this->directory->company($employee->company_id);

        return $this->suitePage('Hr/Employees/Show', [
            'employee' => new EmployeeResource($employee, $can),
            'company' => $company?->name,
            'branch' => $branchNames[$employee->branch_id] ?? null,
            'login' => $employee->user_id ? app(Accounts::class)->summary($employee->user_id) : null,
            'suggested_role' => ($roleId = $employee->job?->default_role_id) ? collect(app(Access::class)->roles())->firstWhere('id', $roleId)['name'] ?? null : null,
            'device' => ($device = AttendanceController::deviceFor($employee)) ? ['model' => $device->model, 'platform' => $device->platform, 'bound_at' => $device->bound_at->toDateString()] : null,
            'contracts' => $can['view_salaries'] ? ContractResource::collection($employee->contracts) : [],
            'documents' => $can['view_documents'] ? EmployeeDocumentResource::collection($employee->documents) : [],
            'movements' => $can['view_movements'] ? MovementResource::collection($employee->movements)->map(fn ($m) => (new MovementResource($m->resource, $branchNames))->resolve()) : [],
            'audit' => app(Audit::class)->history($employee),
            'statuses' => EmployeeStatus::options(),
            'branches' => collect($this->directory->branches())->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
            'allowance_types' => CatalogResource::collection(AllowanceType::query()->where('is_active', true)->orderBy('sort_order')->get()),
            'pay' => $can['view_pay'] ? [
                'bonuses' => Bonus::query()->where('employee_id', $employee->id)->with('type')->latest('pay_month')->limit(24)->get()->map(fn (Bonus $bonus): array => ['id' => $bonus->id, 'type' => $bonus->type->localized('name'), 'amount' => (string) $bonus->amount, 'pay_month' => $bonus->pay_month->format('Y-m'), 'status' => $bonus->status->value, 'reason' => $bonus->reason])->all(),
                'deductions' => Deduction::query()->where('employee_id', $employee->id)->with('type')->latest('pay_month')->limit(24)->get()->map(fn (Deduction $deduction): array => ['id' => $deduction->id, 'type' => $deduction->type->localized('name'), 'amount' => (string) $deduction->amount, 'pay_month' => $deduction->pay_month->format('Y-m'), 'status' => $deduction->status->value, 'reason' => $deduction->reason, 'origin' => $deduction->origin])->all(),
                'advances' => Advance::query()->where('employee_id', $employee->id)->with(['schedule', 'repayments'])->latest()->get()->map(fn (Advance $advance): array => [
                    'id' => $advance->id, 'amount' => (string) $advance->amount, 'installments' => $advance->installments, 'status' => $advance->status->value, 'balance' => $advance->balance(),
                    'first_installment_month' => $advance->first_installment_month->format('Y-m'), 'reason' => $advance->reason,
                    'schedule' => $advance->schedule->map(fn ($line): array => ['due_month' => $line->due_month->format('Y-m'), 'amount' => (string) $line->amount, 'paid' => (string) $line->paid, 'status' => $line->status])->all(),
                    'repayments' => $advance->repayments->map(fn ($line): array => ['paid_on' => $line->paid_on->toDateString(), 'amount' => (string) $line->amount, 'method' => $line->method->value])->all(),
                ])->all(),
                'bonus_types' => BonusType::query()->where('is_active', true)->get()->map(fn (BonusType $type): array => ['value' => $type->id, 'label' => $type->localized('name')])->all(),
                'deduction_types' => DeductionType::query()->where('is_active', true)->get()->map(fn (DeductionType $type): array => ['value' => $type->id, 'label' => $type->localized('name')])->all(),
            ] : null,
            'leave' => $can['view_leave'] ? [
                'requests' => $employee->leaveRequests()->with('type')->latest('starts_on')->limit(24)->get()->map(fn ($leave): array => LeaveController::leaveArray($leave))->all(),
                'balances' => LeaveType::query()->where('is_active', true)->where('needs_balance', true)->get()->map(fn (LeaveType $type): array => ['type_id' => $type->id, 'type' => $type->localized('name'), ...app(LeaveBalance::class)->handle($employee, $type)])->all(),
                'types' => LeaveType::query()->where('is_active', true)->get()->map(fn (LeaveType $type): array => ['value' => $type->id, 'label' => $type->localized('name')])->all(),
            ] : null,
            'document_types' => CatalogResource::collection(DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get()),
            'letters' => $can['view_documents'] ? Letter::query()->where('employee_id', $employee->id)->latest('issued_at')->limit(20)->get()->map(fn (Letter $letter): array => [
                'id' => $letter->id, 'reference' => $letter->reference, 'kind' => $letter->kind->value, 'language' => $letter->language,
                'addressed_to' => $letter->addressed_to, 'issued_at' => $letter->issued_at->toDateString(), 'valid' => $letter->isValid(),
            ])->all() : [],
            'can_salary_letter' => $this->able($employee, HrFeature::Employees, 'view_salaries'),
            'end_of_service' => $can['view_eosb'] && $employee->hired_on ? $this->endOfService($employee) : null,
            'pending_changes' => ChangeRequest::query()->where('employee_id', $employee->id)->where('status', ChangeRequestStatus::Pending->value)->latest()->get()
                ->map(fn (ChangeRequest $change): array => [
                    'id' => $change->id, 'kind' => $change->kind, 'summary' => $change->summary, 'created_at' => $change->created_at->toDateTimeString(),
                    'requested_by' => $change->requested_by ? app(Accounts::class)->summary($change->requested_by)['name'] ?? null : null,
                    'can_approve' => $this->able($employee, HrFeature::Employees, 'approve_changes') && $change->requested_by !== auth()->id(),
                    'can_reject' => $this->able($employee, HrFeature::Employees, 'approve_changes') || $change->requested_by === auth()->id(),
                ])->all(),
        ], ['hr']);
    }

    public function edit(Employee $employee): Response
    {
        $this->authorizeEmployee($employee, HrFeature::Employees, FeatureAction::Update);

        return $this->suitePage('Hr/Employees/Form', [
            'employee' => new EmployeeResource($employee, $this->abilities($employee)),
            ...$this->formOptions(FeatureAction::Update),
        ], ['hr']);
    }

    public function update(SaveEmployeeRequest $request, Employee $employee, SaveEmployee $saveEmployee): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Employees, FeatureAction::Update);

        $data = collect($request->validated())->except(['company_id', 'branch_id', 'status']);

        if (! $this->able($employee, HrFeature::Employees, 'view_iban')) {
            $data->forget('iban');
        }

        if (! $this->able($employee, HrFeature::Employees, 'view_national_id')) {
            $data->forget('national_id');
        }

        $saveEmployee->handle($employee, $data->all(), $request->user());
        $this->toast(app(DualControl::class)->held() === [] ? __('hr.employees.saved') : __('hr.changes.held'));

        return to_route('hr.employees.show', $employee);
    }

    public function status(Request $request, Employee $employee, ChangeEmployeeStatus $changeStatus): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Employees, FeatureAction::Update);
        $data = $request->validate(['status' => ['required', Rule::enum(EmployeeStatus::class)], 'terminated_on' => ['nullable', 'date']]);

        $changeStatus->handle($employee, EmployeeStatus::from($data['status']), $request->user(), $data['terminated_on'] ?? null);
        $this->toast(__('hr.employees.status_changed'));

        return back();
    }

    public function linkLogin(Request $request, Employee $employee, LinkEmployeeUser $linkEmployeeUser): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Employees, 'link_login');
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'grant_job_role' => ['boolean']]);

        $linkEmployeeUser->handle($employee, $data['email'], $request->user(), (bool) ($data['grant_job_role'] ?? false));
        $this->toast(__('hr.employees.login_linked'));

        return back();
    }

    /**
     * The award if they left today (or on their last day), net of open advances, and the
     * recorded final settlement once there is one.
     *
     * @return array<string, mixed>
     */
    private function endOfService(Employee $employee): array
    {
        $settle = app(SettleFinalAccount::class);
        $settlement = FinalSettlement::query()->where('employee_id', $employee->id)->first();

        return [
            'previews' => collect(['termination', 'resignation', 'contract_end'])->mapWithKeys(fn (string $reason): array => [$reason => $settle->preview($employee, $reason)])->all(),
            'settlement' => $settlement ? [
                'reason' => $settlement->reason, 'last_day' => $settlement->last_day->toDateString(), 'eosb_award' => (string) $settlement->eosb_award,
                'advances_recovered' => (string) $settlement->advances_recovered, 'advances_left' => (string) $settlement->advances_left, 'net_payable' => (string) $settlement->net_payable,
            ] : null,
            'can_settle' => $settlement === null && $employee->status === EmployeeStatus::Terminated && $this->able($employee, HrFeature::Pay, FeatureAction::Approve->value),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Employee $employee): array
    {
        $at = fn (HrFeature $feature, FeatureAction|string $action): bool => $this->reach($feature, $action)->allowsBranch($employee->branch_id, $employee->company_id);

        return [
            'view_salaries' => $this->able($employee, HrFeature::Employees, 'view_salaries') || $this->able($employee, HrFeature::Contracts, 'view_salaries'),
            'view_iban' => $this->able($employee, HrFeature::Employees, 'view_iban'),
            'view_national_id' => $this->able($employee, HrFeature::Employees, 'view_national_id'),
            'update' => $at(HrFeature::Employees, FeatureAction::Update),
            'link_login' => $this->able($employee, HrFeature::Employees, 'link_login'),
            'view_documents' => $at(HrFeature::Documents, FeatureAction::View),
            'view_movements' => $at(HrFeature::Movements, FeatureAction::View),
            'manage_contracts' => $at(HrFeature::Contracts, FeatureAction::Create) || $at(HrFeature::Contracts, FeatureAction::Update),
            'manage_documents' => $at(HrFeature::Documents, FeatureAction::Create) || $at(HrFeature::Documents, FeatureAction::Update),
            'manage_movements' => $at(HrFeature::Movements, FeatureAction::Create),
            'reset_device' => $this->able($employee, HrFeature::Attendance, 'reset_device'),
            'view_pay' => $at(HrFeature::Pay, FeatureAction::View),
            'manage_pay' => $at(HrFeature::Pay, FeatureAction::Create),
            'approve_pay' => $at(HrFeature::Pay, FeatureAction::Approve),
            'view_leave' => $at(HrFeature::Leave, FeatureAction::View),
            'manage_leave' => $at(HrFeature::Leave, FeatureAction::Create),
            'approve_leave' => $at(HrFeature::Leave, FeatureAction::Approve),
            'view_eosb' => $this->able($employee, HrFeature::Payroll, 'view_eosb'),
        ];
    }

    /**
     * Companies and branches the user may place people at, plus the catalogs.
     *
     * @return array<string, mixed>
     */
    private function formOptions(FeatureAction $action): array
    {
        $reach = $this->reach(HrFeature::Employees, $action);
        $branches = collect($this->directory->branches())->filter(fn (BranchRef $branch): bool => $branch->isActive && $reach->allowsBranch($branch->id, $branch->companyId));
        $companyIds = $branches->pluck('companyId')->unique();

        return [
            'companies' => collect($this->directory->companies())->filter(fn (CompanyRef $company): bool => $companyIds->contains($company->id))
                ->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->values()->all(),
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->values()->all(),
            'sections' => CatalogResource::collection(Section::query()->where('is_active', true)->orderBy('sort_order')->get()),
            'jobs' => CatalogResource::collection(Job::query()->where('is_active', true)->with('sections')->orderBy('sort_order')->get()),
            'managers' => $this->employees()->whereIn('status', EmployeeStatus::seated())->orderBy('name_en')->get(['id', 'name_ar', 'name_en'])
                ->map(fn (Employee $manager): array => ['value' => $manager->id, 'label' => $manager->localized('name')])->all(),
            'statuses' => EmployeeStatus::options(),
            'genders' => Gender::options(),
            'id_types' => IdType::options(),
        ];
    }
}
