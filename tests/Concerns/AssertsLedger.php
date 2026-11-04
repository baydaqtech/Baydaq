<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Checks on what reached the general ledger.
 */
trait AssertsLedger
{
    /** One balanced journal entry was posted for this source. */
    protected function assertPostedBalanced(string $source, int $sourceId): void
    {
        $entries = DB::table('finance__journal_entries')->where('source', $source)->where('source_id', $sourceId)->pluck('id');
        $this->assertCount(1, $entries);
        $lines = DB::table('finance__journal_lines')->whereIn('entry_id', $entries);
        $this->assertEquals((clone $lines)->sum('debit'), (clone $lines)->sum('credit'));
        $this->assertGreaterThan(0, (clone $lines)->sum('debit'));
    }

    /** Debits less credits on one of the system accounts of a company, e.g. "cash" or "sales". */
    protected function ledgerBalance(int $companyId, string $systemKey): string
    {
        return number_format((float) DB::table('finance__journal_lines')
            ->join('finance__journal_entries', 'finance__journal_entries.id', '=', 'finance__journal_lines.entry_id')
            ->join('finance__accounts', 'finance__accounts.id', '=', 'finance__journal_lines.account_id')
            ->where('finance__journal_entries.company_id', $companyId)
            ->where('finance__accounts.system_key', $systemKey)
            ->sum(DB::raw('finance__journal_lines.debit - finance__journal_lines.credit')), 2, '.', '');
    }
}
