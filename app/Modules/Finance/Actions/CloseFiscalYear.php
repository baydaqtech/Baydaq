<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FiscalClose;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Ports\OrgDirectory;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Close a finished year of one company: every revenue and expense account of that year is
 * brought to zero by one closing entry on 31 December, and the profit (or loss) lands in
 * retained earnings. Afterwards no manual entry may be dated in that year.
 */
class CloseFiscalYear
{
    public const SOURCE = 'finance.year_close';

    public function __construct(private OrgDirectory $directory) {}

    public function handle(int $companyId, int $year, ?Authenticatable $actor = null): FiscalClose
    {
        if ($year >= (int) substr($this->directory->today($companyId), 0, 4)) {
            throw ValidationException::withMessages(['year' => __('finance.errors.year_not_over')]);
        }

        try {
            return DB::transaction(function () use ($companyId, $year, $actor): FiscalClose {
                // The unique (company, year) row is taken first: a second close of the same year fails here.
                $close = (new FiscalClose)->forceFill(['company_id' => $companyId, 'year' => $year, 'profit' => '0', 'closed_by' => $actor?->getAuthIdentifier()]);
                $close->save();

                $accounts = ChartOfAccounts::ensure();
                $totals = JournalLine::query()
                    ->whereHas('entry', fn ($query) => $query->where('company_id', $companyId)->whereYear('entry_date', $year)->where('source', '!=', self::SOURCE))
                    ->whereHas('account', fn ($query) => $query->whereIn('type', ['revenue', 'expense']))
                    ->selectRaw('account_id, sum(debit) as debit, sum(credit) as credit')->groupBy('account_id')->get();
                $lines = [];
                $profit = BigDecimal::zero();

                foreach ($totals as $total) {
                    // What the account holds, turned the other way to bring it to zero.
                    $balance = BigDecimal::of((string) $total->credit)->minus((string) $total->debit);

                    if ($balance->isZero()) {
                        continue;
                    }

                    $profit = $profit->plus($balance);
                    $lines[] = ['account_id' => $total->account_id, 'debit' => $balance->isPositive() ? $balance : BigDecimal::zero(), 'credit' => $balance->isNegative() ? $balance->abs() : BigDecimal::zero()];
                }

                if ($lines !== []) {
                    $lines[] = ['account_id' => $accounts['retained_earnings']->id, 'debit' => $profit->isNegative() ? $profit->abs() : BigDecimal::zero(), 'credit' => $profit->isPositive() ? $profit : BigDecimal::zero()];
                    $entry = (new JournalEntry)->forceFill([
                        'company_id' => $companyId, 'entry_date' => "{$year}-12-31", 'source' => self::SOURCE, 'source_id' => $close->id,
                        'memo' => __('finance.periods.close_memo', ['year' => $year]), 'created_by' => $actor?->getAuthIdentifier(),
                    ]);
                    $entry->save();

                    foreach ($lines as $line) {
                        $entry->lines()->make()->forceFill(['account_id' => $line['account_id'], 'debit' => (string) $line['debit']->toScale(2), 'credit' => (string) $line['credit']->toScale(2)])->save();
                    }

                    $close->forceFill(['entry_id' => $entry->id]);
                }

                $close->forceFill(['profit' => (string) $profit->toScale(2)])->save();

                return $close;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['year' => __('finance.errors.year_closed')]);
        }
    }
}
