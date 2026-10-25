<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Payroll\ApprovePayrollRun;
use App\Modules\Hr\Actions\Payroll\BuildPayrollRun;
use App\Modules\Hr\Actions\Payroll\EndOfServiceBenefit;
use App\Modules\Hr\Actions\Payroll\EosbLiability;
use App\Modules\Hr\Actions\Payroll\PayrollDiff;
use App\Modules\Hr\Actions\Payroll\SettleFinalAccount;
use App\Modules\Hr\Actions\Payroll\WpsFile;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\PayrollStatus;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\PayrollLine;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Payroll is per company: a run needs the payroll key over the whole company.
 */
class PayrollController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory) {}

    public function index(): Response
    {
        $companies = collect($this->directory->companies())->filter(fn (CompanyRef $company): bool => $this->reach(HrFeature::Payroll, FeatureAction::View)->allowsWholeCompany($company->id));

        $runs = PayrollRun::query()->whereIn('company_id', $companies->pluck('id'))->orderByDesc('pay_month')->get()
            ->map(fn (PayrollRun $run): array => $this->runArray($run, $companies->firstWhere('id', $run->company_id)));

        return $this->suitePage('Hr/Payroll/Index', [
            'runs' => $runs->values()->all(),
            'companies' => $companies->filter(fn (CompanyRef $company): bool => $this->reach(HrFeature::Payroll, FeatureAction::Create)->allowsWholeCompany($company->id))
                ->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->values()->all(),
        ], ['hr']);
    }

    public function build(Request $request, BuildPayrollRun $build): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer'], 'pay_month' => ['required', 'date']]);
        abort_unless($this->reach(HrFeature::Payroll, FeatureAction::Create)->allowsWholeCompany((int) $data['company_id']), 403);

        $run = $build->handle((int) $data['company_id'], $data['pay_month'], $request->user());
        $this->toast(__('hr.payroll.built'));

        return to_route('hr.payroll.show', $run);
    }

    public function show(PayrollRun $run): Response
    {
        abort_unless($this->reach(HrFeature::Payroll, FeatureAction::View)->allowsWholeCompany($run->company_id), 403);
        $branchNames = collect($this->directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        return $this->suitePage('Hr/Payroll/Show', [
            'run' => $this->runArray($run, $this->directory->company($run->company_id)),
            'lines' => $run->lines()->with('employee:id,name_ar,name_en,employee_number')->get()->map(fn (PayrollLine $line): array => [
                'id' => $line->id,
                'employee_id' => $line->employee_id,
                'employee' => $line->employee->localized('name'),
                'employee_number' => $line->employee->employee_number,
                'branch' => $branchNames[$line->branch_id] ?? '—',
                'basic' => (string) $line->basic, 'allowances' => (string) $line->allowances, 'bonuses' => (string) $line->bonuses,
                'deductions' => (string) $line->deductions, 'advance_installments' => (string) $line->advance_installments,
                'unpaid_leave' => (string) $line->unpaid_leave, 'gosi_employee' => (string) $line->gosi_employee, 'gosi_employer' => (string) $line->gosi_employer,
                'gross' => (string) $line->gross, 'net' => (string) $line->net, 'breakdown' => $line->breakdown,
            ])->all(),
            'diff' => app(PayrollDiff::class)->handle($run),
            'can' => [
                'approve' => $run->status === PayrollStatus::Draft && $this->reach(HrFeature::Payroll, FeatureAction::Approve)->allowsWholeCompany($run->company_id),
                'rebuild' => $run->status === PayrollStatus::Draft && $this->reach(HrFeature::Payroll, FeatureAction::Create)->allowsWholeCompany($run->company_id),
                'mark_paid' => $run->status === PayrollStatus::Approved && $this->access()->allowsBranch(HrFeature::Payroll->key('mark_paid'), 0, $run->company_id),
                'export' => $run->status !== PayrollStatus::Draft && $this->reach(HrFeature::Payroll, FeatureAction::Export)->allowsWholeCompany($run->company_id),
            ],
        ], ['hr']);
    }

    public function approve(Request $request, PayrollRun $run, ApprovePayrollRun $approve): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Payroll, FeatureAction::Approve)->allowsWholeCompany($run->company_id), 403);
        $approve->approve($run, $request->user());
        $this->toast(__('hr.payroll.approved'));

        return back();
    }

    public function markPaid(Request $request, PayrollRun $run, ApprovePayrollRun $approve): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(HrFeature::Payroll->key('mark_paid'), 0, $run->company_id), 403);
        $data = $request->validate(['paid_on' => ['required', 'date']]);
        $approve->markPaid($run, $data['paid_on'], $request->user());
        $this->toast(__('hr.payroll.paid'));

        return back();
    }

    public function wps(PayrollRun $run, WpsFile $wps): HttpResponse
    {
        abort_unless($run->status !== PayrollStatus::Draft && $this->reach(HrFeature::Payroll, FeatureAction::Export)->allowsWholeCompany($run->company_id), 403);

        return response($wps->handle($run), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wps-'.$run->pay_month->format('Y-m').'-'.$run->company_id.'.csv"',
        ]);
    }

    public function eosb(Request $request, Employee $employee, EndOfServiceBenefit $eosb): JsonResponse
    {
        abort_unless($this->able($employee, HrFeature::Payroll, 'view_eosb'), 403);
        $data = $request->validate(['reason' => ['required', Rule::in(['termination', 'resignation', 'contract_end', 'retirement'])], 'last_day' => ['nullable', 'date']]);

        return response()->json($eosb->handle($employee, $data['reason'], isset($data['last_day']) ? Carbon::parse($data['last_day']) : null));
    }

    /** Close a leaver's account: the award less open advances (H16). */
    public function settle(Request $request, Employee $employee, SettleFinalAccount $settle): RedirectResponse
    {
        abort_unless($this->able($employee, HrFeature::Payroll, 'view_eosb') && $this->able($employee, HrFeature::Pay, FeatureAction::Approve->value), 403);
        $data = $request->validate(['reason' => ['required', Rule::in(['termination', 'resignation', 'contract_end', 'retirement'])]]);

        $settle->settle($employee, $data['reason'], $request->user());
        $this->toast(__('hr.payroll.settled'));

        return back();
    }

    /** End-of-service liability today, per company and per person, over the user's reach (H10). */
    public function liability(OrgDirectory $directory, EosbLiability $liability): Response
    {
        $report = $liability->handle($this->reach(HrFeature::Payroll, 'view_eosb'));
        $companyNames = collect($directory->companies())->mapWithKeys(fn (CompanyRef $company): array => [$company->id => $company->name]);

        return $this->suitePage('Hr/Reports/EosbLiability', [
            'total' => $report['total'],
            'companies' => array_map(fn (array $row): array => [...$row, 'company' => $companyNames[$row['company_id']] ?? '—'], $report['companies']),
            'employees' => array_map(fn (array $row): array => [...$row, 'company' => $companyNames[$row['company_id']] ?? '—'], $report['employees']),
        ], ['hr']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runArray(PayrollRun $run, ?CompanyRef $company): array
    {
        return [
            'id' => $run->id,
            'company' => $company?->name,
            'pay_month' => $run->pay_month->format('Y-m'),
            'status' => $run->status->value,
            'employees_count' => $run->employees_count,
            'total_gross' => (string) $run->total_gross,
            'total_deductions' => (string) $run->total_deductions,
            'total_net' => (string) $run->total_net,
            'total_employer_gosi' => (string) $run->total_employer_gosi,
            'paid_on' => $run->paid_on?->toDateString(),
        ];
    }
}
