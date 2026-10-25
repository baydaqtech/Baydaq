<?php

namespace App\Modules\Finance\Adapters;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Ledger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The ledger behind the Ledger port: each posting becomes one balanced journal entry, recorded
 * once per source. Without accounting in the package nothing is recorded.
 */
class GeneralLedger implements Ledger
{
    public function __construct(private Entitlements $entitlements) {}

    public function post(LedgerPosting $posting): void
    {
        if (! $this->entitlements->hasFeature('finance.accounting')) {
            return;
        }

        if (JournalEntry::query()->where('source', $posting->source)->where('source_id', $posting->sourceId)->where('is_reversal', false)->exists()) {
            return;
        }

        $lines = array_values(array_filter($posting->lines, fn (array $line): bool => BigDecimal::of($line['debit'] ?? '0')->isZero() === false || BigDecimal::of($line['credit'] ?? '0')->isZero() === false));
        $debits = array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['debit'] ?? '0'), BigDecimal::zero());
        $credits = array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['credit'] ?? '0'), BigDecimal::zero());

        // A bad posting is reported, never allowed to block the sale or payroll that caused it.
        if (! $debits->isEqualTo($credits)) {
            report(new LogicException("Unbalanced posting {$posting->source}#{$posting->sourceId}: {$debits} ≠ {$credits}"));

            return;
        }

        if ($lines === []) {
            return;
        }

        DB::transaction(function () use ($posting, $lines): void {
            $accounts = ChartOfAccounts::ensure();
            $entry = (new JournalEntry)->forceFill([
                'company_id' => $posting->companyId, 'branch_id' => $posting->branchId, 'entry_date' => $posting->date,
                'source' => $posting->source, 'source_id' => $posting->sourceId, 'memo' => mb_substr($posting->memo, 0, 255),
                'created_by' => auth()->id(),
            ]);
            $entry->save();

            foreach ($lines as $line) {
                $account = (str_starts_with($line['account'], '#') ? Account::query()->find((int) substr($line['account'], 1)) : $accounts->get($line['account']))
                    ?? throw new LogicException("Unknown ledger account {$line['account']}");
                $entry->lines()->make()->forceFill([
                    'account_id' => $account->id, 'debit' => $line['debit'] ?? '0', 'credit' => $line['credit'] ?? '0',
                    'tax_base' => $line['tax_base'] ?? null, 'memo' => $line['memo'] ?? null,
                ])->save();
            }
        });
    }

    public function reverse(string $source, int $sourceId, string $date): void
    {
        $original = JournalEntry::query()->with('lines')->where('source', $source)->where('source_id', $sourceId)->where('is_reversal', false)->first();

        if ($original === null || JournalEntry::query()->where('source', $source)->where('source_id', $sourceId)->where('is_reversal', true)->exists()) {
            return;
        }

        DB::transaction(function () use ($original, $date): void {
            $reversal = (new JournalEntry)->forceFill([
                'company_id' => $original->company_id, 'branch_id' => $original->branch_id, 'entry_date' => $date,
                'source' => $original->source, 'source_id' => $original->source_id, 'memo' => mb_substr('↩ '.$original->memo, 0, 255),
                'is_reversal' => true, 'created_by' => auth()->id(),
            ]);
            $reversal->save();

            foreach ($original->lines as $line) {
                $reversal->lines()->make()->forceFill([
                    'account_id' => $line->account_id, 'debit' => $line->credit, 'credit' => $line->debit,
                    'tax_base' => $line->tax_base !== null ? (string) BigDecimal::of((string) $line->tax_base)->negated() : null, 'memo' => $line->memo,
                ])->save();
            }
        });
    }
}
