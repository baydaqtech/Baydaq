<?php

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Models\Bill;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Each morning, whoever pays a company's supplier bills hears about the approved bills due within
 * three days (or already late), one alert per company.
 */
class NotifyBillsDue extends Command
{
    protected $signature = 'finance:notify-bills-due';

    protected $description = 'Alert bill payers about supplier bills due within three days';

    public function handle(CurrentTenant $tenant, Access $access, Accounts $accounts, OrgDirectory $directory): int
    {
        $until = CarbonImmutable::today('Asia/Riyadh')->addDays(3);
        $due = Bill::withoutGlobalScopes()->where('status', 'approved')->whereColumn('paid', '<', 'total')->whereDate('due_on', '<=', $until)->get()->groupBy('client_id');

        foreach ($due as $clientId => $bills) {
            $tenant->runFor($clientId, function () use ($bills, $access, $accounts, $directory): void {
                if (! app(Entitlements::class)->hasFeature('finance.purchasing')) {
                    return;
                }

                foreach ($bills->groupBy('company_id') as $companyId => $companyBills) {
                    $payers = $companyBills->flatMap(fn (Bill $bill) => $access->usersWith(FinanceFeature::Payables->key('pay'), $bill->branch_id)->pluck('id'))->unique();
                    $total = $companyBills->reduce(fn (BigDecimal $sum, Bill $bill) => $sum->plus((string) $bill->total)->minus((string) $bill->paid), BigDecimal::zero());

                    foreach ($payers as $userId) {
                        $accounts->alert($userId, 'finance.bills_due', [
                            'count' => $companyBills->count(),
                            'company' => $directory->company($companyId)?->name ?? '',
                            'total' => (string) $total->toScale(2),
                            'date' => $companyBills->max(fn (Bill $bill) => $bill->due_on->toDateString()),
                        ], '/finance/purchasing');
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
