<?php

namespace App\Modules\Subscriptions\Console;

use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Models\Membership;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireMemberships extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark memberships whose last day has passed as expired';

    /**
     * Across tenants, with "today" in Riyadh time (all tenants are in Saudi Arabia for now).
     */
    public function handle(): int
    {
        $expired = Membership::withoutGlobalScopes()->where('status', MembershipStatus::Active)
            ->whereDate('ends_on', '<', Carbon::today('Asia/Riyadh'))
            ->update(['status' => MembershipStatus::Expired->value, 'updated_at' => now()]);

        $this->info("{$expired} membership(s) expired.");

        return self::SUCCESS;
    }
}
