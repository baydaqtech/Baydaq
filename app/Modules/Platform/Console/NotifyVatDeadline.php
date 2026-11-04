<?php

namespace App\Modules\Platform\Console;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * A week and a day before the quarterly VAT return is due (the end of January, April, July and
 * October), people who see invoices or finance reports are reminded.
 */
class NotifyVatDeadline extends Command
{
    protected $signature = 'suite:notify-vat';

    protected $description = 'Remind about the quarterly VAT return a week and a day before it is due';

    private const DAYS_BEFORE = [7, 1];

    public function handle(CurrentTenant $tenant, AccessResolver $resolver, Accounts $accounts): int
    {
        $today = CarbonImmutable::today('Asia/Riyadh');
        $due = $today->endOfMonth()->startOfDay();
        $daysLeft = (int) $today->diffInDays($due);

        if (! in_array($today->month, [1, 4, 7, 10], true) || ! in_array($daysLeft, self::DAYS_BEFORE, true)) {
            return self::SUCCESS;
        }

        foreach (AccessAssignment::withoutGlobalScopes()->distinct()->pluck('client_id') as $clientId) {
            $tenant->runFor($clientId, function () use ($clientId, $resolver, $accounts, $due, $daysLeft): void {
                if (! app(Entitlements::class)->hasFeature('suite.gov')) {
                    return;
                }

                foreach (User::query()->where('client_id', $clientId)->whereHas('accessAssignments')->get() as $user) {
                    $map = $resolver->for($user);

                    if ($map->allows('invoicing.invoices.view') || $map->allows('finance.reports.view')) {
                        $accounts->alert($user->id, 'gov.vat_due', ['date' => $due->toDateString(), 'days' => $daysLeft], '/gov');
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
