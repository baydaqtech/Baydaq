<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Actions\Messages\MessageBuilder;
use App\Modules\Hr\Models\MessageTemplate;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendScheduledMessages extends Command
{
    protected $signature = 'hr:send-messages';

    protected $description = 'Send message-builder templates due today (weekly, monthly, birthdays, anniversaries, contracts ending)';

    public function handle(CurrentTenant $tenant, MessageBuilder $builder): int
    {
        $today = CarbonImmutable::today('Asia/Riyadh');
        $sent = 0;

        foreach (MessageTemplate::withoutGlobalScopes()->where('is_active', true)->where('trigger', '!=', 'manual')->distinct()->pluck('client_id') as $clientId) {
            $tenant->runFor($clientId, function () use ($builder, $today, &$sent): void {
                $sent += $builder->runDaily($today);
            });
        }

        $this->info("{$sent} message(s) sent.");

        return self::SUCCESS;
    }
}
