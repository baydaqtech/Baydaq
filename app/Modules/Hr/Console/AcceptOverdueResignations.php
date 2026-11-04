<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Actions\Offboarding\SeparationFlow;
use App\Modules\Hr\Models\Separation;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

class AcceptOverdueResignations extends Command
{
    protected $signature = 'hr:accept-overdue-resignations';

    protected $description = 'Mark resignations the employer did not answer in time as accepted (Labor Law, 2025 amendments)';

    public function handle(CurrentTenant $tenant, SeparationFlow $flow): int
    {
        $clientIds = Separation::withoutGlobalScopes()->whereIn('status', ['submitted', 'deferred'])->whereDate('deemed_accept_on', '<=', today())->distinct()->pluck('client_id');
        $accepted = 0;

        foreach ($clientIds as $clientId) {
            $tenant->runFor($clientId, function () use ($flow, &$accepted): void {
                $accepted += $flow->acceptOverdue();
            });
        }

        $this->info("{$accepted} resignation(s) accepted.");

        return self::SUCCESS;
    }
}
