<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Expenses\ExpenseFlow;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expense claims to approve and pay back, with each receipt.
 */
class ExpenseController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'submitted');
        $reach = $this->reach(HrFeature::Expenses, FeatureAction::View);
        $approve = $this->reach(HrFeature::Expenses, FeatureAction::Approve);
        $pay = $this->reach(HrFeature::Expenses, 'pay');
        $claims = $reach->apply(ExpenseClaim::query())->with('employee')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('spent_on')->limit(200)->get();

        return $this->suitePage('Hr/Expenses/Index', [
            'claims' => $claims->map(fn (ExpenseClaim $claim): array => [
                ...self::claimArray($claim), 'employee' => $claim->employee->localized('name'),
                'can_decide' => $approve->allowsBranch($claim->branch_id, $claim->company_id),
                'can_pay' => $pay->allowsBranch($claim->branch_id, $claim->company_id),
            ])->all(),
            'totals' => ['submitted' => (string) $reach->apply(ExpenseClaim::query())->where('status', 'submitted')->sum('amount'), 'approved' => (string) $reach->apply(ExpenseClaim::query())->where('status', 'approved')->sum('amount')],
            'filters' => ['status' => $status],
        ], ['hr']);
    }

    public function decide(Request $request, ExpenseClaim $claim, ExpenseFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Expenses, FeatureAction::Approve)->allowsBranch($claim->branch_id, $claim->company_id), 403);
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:255']]);
        $flow->decide($claim, $data['approve'], $data['note'] ?? null, $request->user());
        $this->toast(__('hr.expenses.saved'));

        return back();
    }

    public function pay(Request $request, ExpenseClaim $claim, ExpenseFlow $flow): RedirectResponse
    {
        abort_unless($this->reach(HrFeature::Expenses, 'pay')->allowsBranch($claim->branch_id, $claim->company_id), 403);
        $flow->markPaid($claim, $request->validate(['paid_on' => ['nullable', 'date', 'before_or_equal:today']])['paid_on'] ?? today()->toDateString(), $request->user());
        $this->toast(__('hr.expenses.saved'));

        return back();
    }

    public function receipt(ExpenseClaim $claim): StreamedResponse
    {
        abort_unless($claim->receipt_path && $this->reach(HrFeature::Expenses, FeatureAction::View)->allowsBranch($claim->branch_id, $claim->company_id), 404);

        return Storage::disk(config('baydaq.hr.disk'))->response($claim->receipt_path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function claimArray(ExpenseClaim $claim): array
    {
        return [
            'id' => $claim->id, 'employee_id' => $claim->employee_id, 'spent_on' => $claim->spent_on->toDateString(), 'category' => $claim->category,
            'amount' => (string) $claim->amount, 'vat' => (string) $claim->vat, 'merchant' => $claim->merchant, 'description' => $claim->description,
            'status' => $claim->status, 'decision_note' => $claim->decision_note, 'paid_on' => $claim->paid_on?->toDateString(), 'has_receipt' => $claim->receipt_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validateClaim(Request $request): array
    {
        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'in:'.implode(',', ExpenseClaim::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'vat' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'merchant_vat_number' => ['nullable', 'digits:15'],
            'description' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        unset($data['receipt']);

        return $data;
    }
}
