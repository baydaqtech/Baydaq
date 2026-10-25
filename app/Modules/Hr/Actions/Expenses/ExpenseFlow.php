<?php

namespace App\Modules\Hr\Actions\Expenses;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\ExpenseClaim;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Expense claims from submission to payback. Approval books the expense (and claimable input VAT)
 * as owed to the employee; paying it clears that.
 */
class ExpenseFlow
{
    public function __construct(private Ledger $ledger, private Audit $audit) {}

    /**
     * @param  array{spent_on: string, category: string, amount: string, vat?: ?string, merchant?: ?string, merchant_vat_number?: ?string, description?: ?string}  $data
     */
    public function submit(Employee $employee, array $data, ?UploadedFile $receipt): ExpenseClaim
    {
        $vat = BigDecimal::of((string) ($data['vat'] ?? '0'));

        // Input VAT needs a VAT invoice: a supplier VAT number and no more than 15/115 of the amount.
        if ($vat->isPositive() && (empty($data['merchant_vat_number']) || $vat->isGreaterThan(BigDecimal::of((string) $data['amount'])->multipliedBy(15)->dividedBy(115, 2, RoundingMode::HalfUp)))) {
            throw ValidationException::withMessages(['vat' => __('hr.expenses.errors.vat')]);
        }

        $claim = new ExpenseClaim([...$data, 'vat' => (string) $vat]);
        $claim->forceFill([
            'employee_id' => $employee->id, 'company_id' => $employee->company_id, 'branch_id' => $employee->branch_id,
            'receipt_path' => $receipt?->store("clients/{$employee->client_id}/employees/{$employee->id}/receipts", config('baydaq.hr.disk')),
        ])->save();

        return $claim;
    }

    public function decide(ExpenseClaim $claim, bool $approve, ?string $note, ?Authenticatable $actor = null): ExpenseClaim
    {
        $this->expect($claim, 'submitted');
        $claim->forceFill(['status' => $approve ? 'approved' : 'rejected', 'decision_note' => $note, 'decided_by' => $actor?->getAuthIdentifier(), 'decided_at' => now()])->save();
        $this->audit->record($claim, $approve ? 'approved' : 'rejected', ['amount' => (string) $claim->amount], $actor);

        if ($approve) {
            $net = BigDecimal::of((string) $claim->amount)->minus((string) $claim->vat);
            $this->ledger->post(new LedgerPosting($claim->company_id, $claim->branch_id, $claim->spent_on->toDateString(), 'hr.expense', $claim->id, trim("{$claim->merchant} {$claim->description}") ?: 'Expense claim', [
                ['account' => $claim->category, 'debit' => (string) $net->toScale(2)],
                ['account' => 'vat_input', 'debit' => (string) $claim->vat, 'tax_base' => (string) $net->toScale(2)],
                ['account' => 'employee_reimbursements', 'credit' => (string) $claim->amount],
            ]));
        }

        return $claim;
    }

    public function markPaid(ExpenseClaim $claim, string $paidOn, ?Authenticatable $actor = null): ExpenseClaim
    {
        $this->expect($claim, 'approved');
        $claim->forceFill(['status' => 'paid', 'paid_on' => $paidOn])->save();
        $this->audit->record($claim, 'paid', [], $actor);
        $this->ledger->post(new LedgerPosting($claim->company_id, $claim->branch_id, $paidOn, 'hr.expense_payment', $claim->id, 'Expense claim paid', [
            ['account' => 'employee_reimbursements', 'debit' => (string) $claim->amount],
            ['account' => 'bank', 'credit' => (string) $claim->amount],
        ]));

        return $claim;
    }

    private function expect(ExpenseClaim $claim, string $status): void
    {
        if ($claim->status !== $status) {
            throw ValidationException::withMessages(['status' => __('hr.expenses.errors.status')]);
        }
    }
}
