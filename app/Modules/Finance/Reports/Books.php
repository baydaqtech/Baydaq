<?php

namespace App\Modules\Finance\Reports;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reports read straight from the journal, within what the viewer reaches: trial balance, profit
 * and loss (also by branch), and the VAT return.
 */
class Books
{
    /**
     * @return Builder<JournalLine>
     */
    private function lines(BranchSet $reach, ?string $from, string $to, ?int $companyId, ?int $branchId = null): Builder
    {
        return JournalLine::query()->whereHas('entry', function ($query) use ($reach, $from, $to, $companyId, $branchId): void {
            $reach->apply($query);
            $query->when($from, fn ($query) => $query->whereDate('entry_date', '>=', $from))->whereDate('entry_date', '<=', $to)
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        });
    }

    /**
     * Every account's debits, credits and balance (debit-natured accounts positive when in debit).
     *
     * @return list<array{account_id: int, code: string, name: string, type: string, debit: string, credit: string, balance: string}>
     */
    public function trialBalance(BranchSet $reach, ?string $from, string $to, ?int $companyId, ?int $branchId = null): array
    {
        $totals = $this->lines($reach, $from, $to, $companyId, $branchId)->selectRaw('account_id, sum(debit) as debit, sum(credit) as credit')->groupBy('account_id')->get()->keyBy('account_id');

        return Account::query()->whereIn('id', $totals->keys())->orderBy('code')->get()->map(function (Account $account) use ($totals): array {
            $debit = BigDecimal::of((string) $totals[$account->id]->debit);
            $credit = BigDecimal::of((string) $totals[$account->id]->credit);

            return [
                'account_id' => $account->id, 'code' => $account->code, 'name' => $account->localized('name'), 'type' => $account->type,
                'debit' => (string) $debit->toScale(2), 'credit' => (string) $credit->toScale(2),
                'balance' => (string) ($account->isDebitNatured() ? $debit->minus($credit) : $credit->minus($debit))->toScale(2),
            ];
        })->all();
    }

    /**
     * Revenue less expenses for the period, by account, and the bottom line.
     *
     * @return array{revenue: list<array{name: string, amount: string}>, expenses: list<array{name: string, amount: string}>, total_revenue: string, total_expenses: string, profit: string}
     */
    public function profitAndLoss(BranchSet $reach, string $from, string $to, ?int $companyId, ?int $branchId = null): array
    {
        $rows = collect($this->trialBalance($reach, $from, $to, $companyId, $branchId));
        $section = fn (string $type): Collection => $rows->where('type', $type)->map(fn (array $row): array => ['name' => $row['code'].' '.$row['name'], 'amount' => $row['balance']])->values();
        $sum = fn (Collection $items): BigDecimal => $items->reduce(fn (BigDecimal $total, array $row) => $total->plus($row['amount']), BigDecimal::zero());
        [$revenue, $expenses] = [$section('revenue'), $section('expense')];

        return [
            'revenue' => $revenue->all(), 'expenses' => $expenses->all(),
            'total_revenue' => (string) $sum($revenue)->toScale(2), 'total_expenses' => (string) $sum($expenses)->toScale(2),
            'profit' => (string) $sum($revenue)->minus($sum($expenses))->toScale(2),
        ];
    }

    /**
     * The VAT return for a period: sales and output VAT, purchases and input VAT, and what is due.
     *
     * @return array{sales: string, output_vat: string, purchases: string, input_vat: string, net_due: string}
     */
    public function vatReturn(BranchSet $reach, string $from, string $to, ?int $companyId): array
    {
        $vat = fn (string $key): object => $this->lines($reach, $from, $to, $companyId)->whereHas('account', fn ($query) => $query->where('system_key', $key))
            ->selectRaw('coalesce(sum(debit),0) as debit, coalesce(sum(credit),0) as credit, coalesce(sum(tax_base),0) as base')->first();
        [$output, $input] = [$vat('vat_output'), $vat('vat_input')];
        $outputVat = BigDecimal::of((string) $output->credit)->minus((string) $output->debit);
        $inputVat = BigDecimal::of((string) $input->debit)->minus((string) $input->credit);

        return [
            'sales' => (string) BigDecimal::of((string) $output->base)->toScale(2), 'output_vat' => (string) $outputVat->toScale(2),
            'purchases' => (string) BigDecimal::of((string) $input->base)->toScale(2), 'input_vat' => (string) $inputVat->toScale(2),
            'net_due' => (string) $outputVat->minus($inputVat)->toScale(2),
        ];
    }
}
