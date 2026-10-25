<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\PurchasingFlow;
use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Http\Controllers\Concerns\ScopesFinance;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\DocumentLine;
use App\Modules\Finance\Models\PurchaseOrder;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Suppliers, purchase orders and supplier bills on one page, per branch.
 */
class PurchasingController extends Controller
{
    use RendersSuitePages, ScopesFinance;

    public function __construct(private OrgDirectory $directory) {}

    public function index(): Response
    {
        ChartOfAccounts::ensure();
        $line = fn (DocumentLine $line): array => [...$line->only(['description', 'account_id']), 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'vat_rate' => (string) $line->vat_rate, 'net' => (string) $line->net];
        $place = fn ($document): array => ['branch' => $this->directory->branch($document->branch_id)?->name, 'supplier' => $document->supplier->name];

        return $this->suitePage('Finance/Purchasing', [
            'suppliers' => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $supplier): array => [
                ...$supplier->only(['id', 'name', 'vat_number', 'cr_number', 'phone', 'email', 'payment_days', 'is_active']),
            ])->all(),
            'orders' => $this->reach(FinanceFeature::Purchasing, FeatureAction::View)->apply(PurchaseOrder::query())->with(['supplier', 'lines'])->latest('order_date')->limit(100)->get()
                ->map(fn (PurchaseOrder $order): array => [
                    ...$order->only(['id', 'number', 'status', 'supplier_id', 'branch_id', 'notes']), ...$place($order),
                    'order_date' => $order->order_date->toDateString(), 'expected_on' => $order->expected_on?->toDateString(), 'total' => (string) $order->total,
                    'lines' => $order->lines->map($line)->all(),
                ])->all(),
            'bills' => $this->reach(FinanceFeature::Payables, FeatureAction::View)->apply(Bill::query())->with(['supplier', 'lines'])->orderByRaw("status = 'paid'")->orderBy('due_on')->limit(100)->get()
                ->map(fn (Bill $bill): array => [
                    ...$bill->only(['id', 'status', 'supplier_id', 'branch_id', 'supplier_invoice_no']), ...$place($bill),
                    'bill_date' => $bill->bill_date->toDateString(), 'due_on' => $bill->due_on->toDateString(), 'overdue' => $bill->status === 'approved' && $bill->due_on->isPast(),
                    'subtotal' => (string) $bill->subtotal, 'vat' => (string) $bill->vat, 'total' => (string) $bill->total, 'balance' => $bill->balance(),
                    'lines' => $bill->lines->map($line)->all(),
                ])->all(),
            'accounts' => Account::query()->where('is_active', true)->whereIn('type', ['expense', 'asset'])->orderBy('code')->get()
                ->map(fn (Account $account): array => ['value' => $account->id, 'label' => $account->code.' '.$account->localized('name')])->all(),
            'can' => [
                'purchase' => $this->access()->allows(FinanceFeature::Purchasing->key(FeatureAction::Create)),
                'approve_orders' => $this->access()->allows(FinanceFeature::Purchasing->key(FeatureAction::Approve)),
                'bill' => $this->access()->allows(FinanceFeature::Payables->key(FeatureAction::Create)),
                'approve_bills' => $this->access()->allows(FinanceFeature::Payables->key(FeatureAction::Approve)),
                'pay' => $this->access()->allows(FinanceFeature::Payables->key('pay')),
            ],
            ...$this->places(FinanceFeature::Purchasing, FeatureAction::Create),
        ], ['finance']);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        (new Supplier($this->validateSupplier($request)))->save();
        $this->toast(__('finance.saved'));

        return back();
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validateSupplier($request));
        $this->toast(__('finance.saved'));

        return back();
    }

    public function storeOrder(Request $request, PurchasingFlow $flow): RedirectResponse
    {
        [$data, $lines, $branch] = $this->validateDocument($request, FinanceFeature::Purchasing, ['order_date' => ['required', 'date'], 'expected_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $flow->saveOrder(new PurchaseOrder, $data, $lines, $branch->companyId, $branch->id, $request->user());
        $this->toast(__('finance.saved'));

        return back();
    }

    public function moveOrder(Request $request, PurchaseOrder $order, PurchasingFlow $flow): RedirectResponse
    {
        $to = $request->validate(['status' => ['required', 'in:approved,received,cancelled,billed'], 'supplier_invoice_no' => ['nullable', 'string', 'max:40'], 'bill_date' => ['nullable', 'date']]);

        if ($to['status'] === 'billed') {
            $this->authorizeBranch(FinanceFeature::Payables, FeatureAction::Create, $order->branch_id, $order->company_id);
            $flow->billOrder($order, $to['supplier_invoice_no'] ?? null, $to['bill_date'] ?? today()->toDateString());
        } else {
            $this->authorizeBranch(FinanceFeature::Purchasing, $to['status'] === 'approved' ? FeatureAction::Approve : FeatureAction::Create, $order->branch_id, $order->company_id);
            $flow->moveOrder($order, $to['status'], $request->user());
        }

        $this->toast(__('finance.saved'));

        return back();
    }

    public function storeBill(Request $request, PurchasingFlow $flow): RedirectResponse
    {
        [$data, $lines, $branch] = $this->validateDocument($request, FinanceFeature::Payables, ['supplier_invoice_no' => ['nullable', 'string', 'max:40'], 'bill_date' => ['required', 'date'], 'due_on' => ['nullable', 'date']]);
        $flow->saveBill(new Bill, $data, $lines, $branch->companyId, $branch->id);
        $this->toast(__('finance.saved'));

        return back();
    }

    public function approveBill(Request $request, Bill $bill, PurchasingFlow $flow): RedirectResponse
    {
        $this->authorizeBranch(FinanceFeature::Payables, FeatureAction::Approve, $bill->branch_id, $bill->company_id);
        $flow->approveBill($bill, $request->user());
        $this->toast(__('finance.saved'));

        return back();
    }

    public function payBill(Request $request, Bill $bill, PurchasingFlow $flow): RedirectResponse
    {
        $this->authorizeBranch(FinanceFeature::Payables, 'pay', $bill->branch_id, $bill->company_id);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'paid_on' => ['required', 'date', 'before_or_equal:today'], 'method' => ['required', 'in:bank,cash'], 'reference' => ['nullable', 'string', 'max:255']]);
        $flow->payBill($bill, (string) $data['amount'], $data['paid_on'], $data['method'], $data['reference'] ?? null, $request->user());
        $this->toast(__('finance.saved'));

        return back();
    }

    /**
     * @param  array<string, list<string>>  $extra
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: BranchRef}
     */
    private function validateDocument(Request $request, FinanceFeature $feature, array $extra): array
    {
        $clientId = $request->user()->client_id;
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer', Rule::exists(Supplier::class, 'id')->where('client_id', $clientId)],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['required', 'numeric', 'in:0,5,15'],
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('client_id', $clientId)],
            ...$extra,
        ]);
        $branch = $this->directory->branch((int) $data['branch_id']) ?? abort(422);
        $this->authorizeBranch($feature, FeatureAction::Create, $branch->id, $branch->companyId);
        $lines = $data['lines'];
        unset($data['lines'], $data['branch_id']);

        return [$data, $lines, $branch];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'digits:15'],
            'cr_number' => ['nullable', 'digits_between:4,20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^SA\d{22}$/'],
            'payment_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ]);
    }
}
