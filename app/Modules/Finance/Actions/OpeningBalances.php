<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FiscalClose;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Support\ChartOfAccounts;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The books as they stood when a company started on Baydaq: cash, bank, stock, equipment, what
 * customers owed and what was owed to suppliers, as one entry per company (source
 * finance.opening). Saving again replaces it. Whatever the balances leave over goes to opening
 * balance equity, so the entry always balances.
 */
class OpeningBalances
{
    public const SOURCE = 'finance.opening';

    /** The company's opening entry, if it has one. */
    public static function entryFor(int $companyId): ?JournalEntry
    {
        return JournalEntry::query()->with('lines.account')->where('source', self::SOURCE)->where('source_id', $companyId)->where('is_reversal', false)->first();
    }

    /**
     * @param  array<int, array{debit?: numeric-string|float|int|null, credit?: numeric-string|float|int|null}>  $balances  account id → amounts
     */
    public function save(int $companyId, string $date, array $balances, ?Authenticatable $actor = null): JournalEntry
    {
        $accounts = ChartOfAccounts::ensure();
        $chosen = Account::query()->whereIn('id', array_keys($balances))->get()->keyBy('id');
        $lines = [];

        foreach ($balances as $accountId => $amounts) {
            $account = $chosen->get($accountId) ?? throw ValidationException::withMessages(['balances' => __('finance.errors.unknown_account')]);
            [$debit, $credit] = [BigDecimal::of((string) ($amounts['debit'] ?? 0) ?: '0'), BigDecimal::of((string) ($amounts['credit'] ?? 0) ?: '0')];

            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }

            if (in_array($account->type, ['revenue', 'expense'], true)) {
                throw ValidationException::withMessages(['balances' => __('finance.errors.opening_balance_sheet', ['account' => $account->code])]);
            }

            $lines[] = ['account_id' => $account->id, 'debit' => $debit->toScale(2), 'credit' => $credit->toScale(2)];
        }

        $difference = array_reduce($lines, fn (BigDecimal $sum, array $line) => $sum->plus($line['debit'])->minus($line['credit']), BigDecimal::zero());

        if (! $difference->isZero()) {
            $lines[] = ['account_id' => $accounts['opening_equity']->id, 'debit' => $difference->isNegative() ? $difference->abs() : BigDecimal::zero(), 'credit' => $difference->isPositive() ? $difference : BigDecimal::zero()];
        }

        if (FiscalClose::covers($companyId, $date)) {
            throw ValidationException::withMessages(['date' => __('finance.errors.year_closed')]);
        }

        return DB::transaction(function () use ($companyId, $date, $lines, $actor): JournalEntry {
            $entry = JournalEntry::query()->where('source', self::SOURCE)->where('source_id', $companyId)->where('is_reversal', false)->lockForUpdate()->first()
                ?? (new JournalEntry)->forceFill(['company_id' => $companyId, 'source' => self::SOURCE, 'source_id' => $companyId, 'created_by' => $actor?->getAuthIdentifier()]);
            $entry->forceFill(['entry_date' => $date, 'memo' => __('finance.periods.opening_memo')])->save();
            $entry->lines()->delete();

            foreach ($lines as $line) {
                $entry->lines()->make()->forceFill(['account_id' => $line['account_id'], 'debit' => (string) $line['debit'], 'credit' => (string) $line['credit']])->save();
            }

            return $entry->load('lines.account');
        });
    }
}
