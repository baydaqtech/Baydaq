<?php

namespace App\Modules\Platform\Console;

use App\Models\User;
use App\Modules\Platform\Actions\Approvals\CollectApprovals;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Once a day, everyone with requests waiting on them gets one alert saying how many.
 */
class NotifyApprovalsWaiting extends Command
{
    protected $signature = 'suite:notify-approvals';

    protected $description = 'Tell each approver how many requests are waiting on them';

    public function handle(CurrentTenant $tenant, CollectApprovals $approvals, Accounts $accounts): int
    {
        $sent = 0;

        foreach (AccessAssignment::withoutGlobalScopes()->distinct()->pluck('client_id') as $clientId) {
            $tenant->runFor($clientId, function () use ($clientId, $approvals, $accounts, &$sent): void {
                foreach (User::query()->where('client_id', $clientId)->whereHas('accessAssignments')->get() as $user) {
                    $count = count($approvals->everywhere($user));

                    if ($count > 0) {
                        $accounts->alert($user->id, 'suite.approvals_waiting', ['count' => $count], '/approvals');
                        $sent++;
                    }
                }
            });
        }

        $this->info("Alerted {$sent} approvers.");

        return self::SUCCESS;
    }
}
