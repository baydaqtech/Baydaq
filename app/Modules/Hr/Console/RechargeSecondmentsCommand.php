<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Actions\Movements\RechargeSecondments;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RechargeSecondmentsCommand extends Command
{
    protected $signature = 'hr:recharge-secondments {month? : YYYY-MM, last month by default}';

    protected $description = 'Draft intercompany invoices for secondments between companies of the same owner';

    public function handle(CurrentTenant $tenant, RechargeSecondments $recharge): int
    {
        $month = $this->argument('month') ? Carbon::parse($this->argument('month').'-01') : Carbon::today('Asia/Riyadh')->subMonthNoOverflow();
        $clientIds = EmployeeMovement::withoutGlobalScopes()->where('type', 'secondment')->distinct()->pluck('client_id');
        $drafts = 0;

        foreach ($clientIds as $clientId) {
            $tenant->runFor($clientId, function () use ($recharge, $month, &$drafts): void {
                $drafts += $recharge->handle($month);
            });
        }

        $this->info("{$drafts} intercompany draft(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
