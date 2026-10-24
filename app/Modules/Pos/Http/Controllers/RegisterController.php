<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Enums\PosFeature;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class RegisterController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(InvoiceIssuer $invoices, StaffDirectory $staff): Response
    {
        $branches = $this->branchesFor(PosFeature::Registers, FeatureAction::View);
        $branchNames = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);
        $companyIds = $branches->pluck('companyId')->unique();

        return $this->suitePage('Pos/Registers/Index', [
            'registers' => Register::query()->whereIn('branch_id', $branches->pluck('id'))->with('openShift.cashier')->orderBy('name')->get()->map(fn (Register $register): array => [
                'id' => $register->id, 'name' => $register->name, 'branch_id' => $register->branch_id, 'branch' => $branchNames->get($register->branch_id), 'einvoice_unit_id' => $register->einvoice_unit_id,
                'is_active' => $register->is_active, 'menu_url' => route('menu.show', $register->menu_token),
                'open_shift' => $register->openShift ? ['id' => $register->openShift->id, 'cashier' => $register->openShift->cashier->name, 'opened_at' => $register->openShift->opened_at->toIso8601String(), 'opening_cash' => (string) $register->openShift->opening_cash] : null,
            ])->all(),
            'cashiers' => Cashier::query()->whereIn('company_id', $companyIds)->orderBy('name')->get()->map(fn (Cashier $cashier): array => ['id' => $cashier->id, 'company_id' => $cashier->company_id, 'employee_id' => $cashier->employee_id, 'name' => $cashier->name, 'is_active' => $cashier->is_active])->all(),
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'company_id' => $branch->companyId])->all(),
            'companies' => $this->companiesFor(PosFeature::Registers, FeatureAction::View)->map(fn ($company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'units' => $companyIds->flatMap(fn (int $companyId) => collect($invoices->unitsFor($companyId))->map(fn (array $unit) => ['value' => $unit['id'], 'label' => $unit['name'], 'company_id' => $companyId]))->values()->all(),
            'staff' => $branches->flatMap(fn (BranchRef $branch) => collect($staff->staffAt($branch->id)))->unique('id')->map(fn ($employee) => ['value' => $employee->id, 'label' => $employee->name, 'company_id' => $employee->companyId])->values()->all(),
            'recent_shifts' => Shift::query()->whereIn('register_id', Register::query()->whereIn('branch_id', $branches->pluck('id'))->select('id'))->whereNotNull('closed_at')->with(['register', 'cashier'])->latest('closed_at')->limit(20)->get()->map(fn (Shift $shift): array => [
                'id' => $shift->id, 'register' => $shift->register->name, 'cashier' => $shift->cashier->name, 'opened_at' => $shift->opened_at->toIso8601String(), 'closed_at' => $shift->closed_at->toIso8601String(),
                'expected_cash' => (string) $shift->expected_cash, 'counted_cash' => (string) $shift->counted_cash, 'difference' => (string) $shift->difference, 'orders_count' => $shift->orders_count, 'sales_total' => (string) $shift->sales_total,
            ])->all(),
        ], ['pos']);
    }

    public function store(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:60'], 'einvoice_unit_id' => ['nullable', 'integer'], 'is_active' => ['boolean']]);
        $branch = $this->branchesFor(PosFeature::Registers, FeatureAction::Create)->first(fn (BranchRef $branch): bool => $branch->id === (int) $data['branch_id']) ?? abort(403);

        (new Register(collect($data)->except('branch_id')->all()))->forceFill(['client_id' => $tenant->client()->id, 'company_id' => $branch->companyId, 'branch_id' => $branch->id, 'menu_token' => Str::random(32)])->save();
        $this->toast(__('pos.registers.saved'));

        return back();
    }

    public function update(Request $request, Register $register): RedirectResponse
    {
        $this->authorizeBranchFor(PosFeature::Registers, FeatureAction::Update, $register->branch_id);
        $register->fill($request->validate(['name' => ['required', 'string', 'max:60'], 'einvoice_unit_id' => ['nullable', 'integer'], 'is_active' => ['boolean']]))->save();
        $this->toast(__('pos.registers.saved'));

        return back();
    }

    public function storeCashier(Request $request, SaveCashier $save): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer'], 'employee_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:255'], 'pin' => ['required', 'digits_between:4,6'], 'is_active' => ['boolean']]);
        abort_unless($this->companiesFor(PosFeature::Registers, FeatureAction::Create)->contains(fn ($company) => $company->id === (int) $data['company_id']), 403);

        $save->handle(new Cashier, collect($data)->except('pin')->all(), $data['pin']);
        $this->toast(__('pos.registers.cashier_saved'));

        return back();
    }

    public function updateCashier(Request $request, Cashier $cashier, SaveCashier $save): RedirectResponse
    {
        abort_unless($this->companiesFor(PosFeature::Registers, FeatureAction::Update)->contains(fn ($company) => $company->id === $cashier->company_id), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'employee_id' => ['nullable', 'integer'], 'pin' => ['nullable', 'digits_between:4,6'], 'is_active' => ['boolean']]);

        $save->handle($cashier, collect($data)->except('pin')->all(), $data['pin'] ?? null);
        $this->toast(__('pos.registers.cashier_saved'));

        return back();
    }

    public function openShift(Request $request, Register $register, ManageShift $shifts): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(PosFeature::Registers->key('open_shift'), $register->branch_id, $register->company_id), 403);
        $data = $request->validate(['cashier_id' => ['required', 'integer'], 'pin' => ['required', 'digits_between:4,6'], 'opening_cash' => ['required', 'numeric', 'min:0']]);

        $shifts->open($register, Cashier::query()->where('company_id', $register->company_id)->findOrFail($data['cashier_id']), $data['pin'], (string) $data['opening_cash']);
        $this->toast(__('pos.registers.shift_opened'));

        return back();
    }

    public function closeShift(Request $request, Shift $shift, ManageShift $shifts): RedirectResponse
    {
        $register = $shift->register;
        abort_unless($this->access()->allowsBranch(PosFeature::Registers->key('close_shift'), $register->branch_id, $register->company_id), 403);
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0']]);

        $shift = $shifts->close($shift, (string) $data['counted_cash']);
        $this->toast(__('pos.registers.shift_closed', ['difference' => (string) $shift->difference]));

        return back();
    }
}
