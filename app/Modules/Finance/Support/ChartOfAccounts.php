<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\Account;
use Illuminate\Support\Collection;

/**
 * The starting chart of accounts, created for an account the first time anything is posted. Each
 * system key is what the modules post to; the owner may rename accounts or add their own.
 */
final class ChartOfAccounts
{
    /** system key => [code, type] */
    public const DEFAULTS = [
        'cash' => ['1100', 'asset'],
        'bank' => ['1110', 'asset'],
        'card_clearing' => ['1120', 'asset'],
        'receivables' => ['1200', 'asset'],
        'employee_receivable' => ['1210', 'asset'],
        'vat_input' => ['1400', 'asset'],
        'payables' => ['2100', 'liability'],
        'salaries_payable' => ['2110', 'liability'],
        'gosi_payable' => ['2120', 'liability'],
        'employee_reimbursements' => ['2130', 'liability'],
        'payroll_withholdings' => ['2140', 'liability'],
        'customer_wallets' => ['2150', 'liability'],
        'vat_output' => ['2200', 'liability'],
        'equity' => ['3100', 'equity'],
        'retained_earnings' => ['3200', 'equity'],
        'sales' => ['4100', 'revenue'],
        'other_income' => ['4900', 'revenue'],
        'purchases' => ['5100', 'expense'],
        'salaries' => ['5200', 'expense'],
        'gosi_expense' => ['5210', 'expense'],
        'rent' => ['5300', 'expense'],
        'utilities' => ['5400', 'expense'],
        'travel' => ['5500', 'expense'],
        'fuel' => ['5510', 'expense'],
        'phone' => ['5520', 'expense'],
        'meals' => ['5600', 'expense'],
        'supplies' => ['5700', 'expense'],
        'maintenance' => ['5800', 'expense'],
        'other_expense' => ['5900', 'expense'],
    ];

    /**
     * The tenant's accounts by system key, creating the starting chart if it is missing.
     *
     * @return Collection<string, Account>
     */
    public static function ensure(): Collection
    {
        $existing = Account::query()->whereNotNull('system_key')->get()->keyBy('system_key');

        foreach (self::DEFAULTS as $key => [$code, $type]) {
            if (! $existing->has($key)) {
                $account = (new Account([
                    'code' => Account::query()->where('code', $code)->exists() ? $code.'-'.$key : $code,
                    'name_ar' => __("finance.accounts.{$key}", [], 'ar'),
                    'name_en' => __("finance.accounts.{$key}", [], 'en'),
                    'type' => $type,
                ]))->forceFill(['system_key' => $key]);
                $account->save();
                $existing->put($key, $account);
            }
        }

        return $existing;
    }
}
