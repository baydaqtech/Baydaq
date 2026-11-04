<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Actions\Movements\ApplyDueMovements;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ApplyMovements extends Command
{
    protected $signature = 'hr:apply-movements';

    protected $description = 'Start the transfers and secondments whose date has come, and complete the secondments that ended';

    /**
     * Runs once per tenant that has pending movements, with "today" in each company's timezone
     * approximated by the app timezone (all tenants are in Saudi Arabia for now).
     */
    public function handle(CurrentTenant $tenant, ApplyDueMovements $apply): int
    {
        $clientIds = EmployeeMovement::withoutGlobalScopes()->whereIn('status', ['scheduled', 'active'])->distinct()->pluck('client_id');
        $applied = 0;

        foreach ($clientIds as $clientId) {
            $tenant->runFor($clientId, function () use ($apply, $clientId, &$applied): void {
                $applied += $apply->handle(Carbon::today('Asia/Riyadh'), $clientId);
            });
        }

        $this->info("{$applied} movement(s) applied.");

        return self::SUCCESS;
    }
}
