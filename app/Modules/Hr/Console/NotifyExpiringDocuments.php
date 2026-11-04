<?php

namespace App\Modules\Hr\Console;

use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\DocumentRenewal;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Hr\Notifications\DocumentExpiring;
use App\Modules\Hr\Support\EmployeeMessenger;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyExpiringDocuments extends Command
{
    protected $signature = 'hr:notify-expiring-documents';

    protected $description = 'Alert the people responsible when an employee document reaches one of its expiry thresholds';

    /**
     * For each document whose next alert date has come: notify everyone who may view documents of
     * that branch (and the employee's own login), open its renewal task, mark the threshold as
     * sent, plan the next one.
     */
    public function handle(CurrentTenant $tenant, Access $access, Accounts $accounts): int
    {
        $today = Carbon::today('Asia/Riyadh');
        $due = EmployeeDocument::withoutGlobalScopes()->whereDate('next_alert_on', '<=', $today)->get()->groupBy('client_id');
        $sent = 0;

        foreach ($due as $clientId => $documents) {
            $tenant->runFor($clientId, function () use ($documents, $access, $accounts, $today, &$sent): void {
                foreach ($documents as $document) {
                    $document = EmployeeDocument::query()->with(['type', 'employee'])->find($document->id);

                    if ($document === null || $document->expires_on === null) {
                        continue;
                    }

                    $daysLeft = (int) $today->diffInDays($document->expires_on, false);
                    $threshold = collect($document->type->alert_days ?? [])->map(fn ($days): int => (int) $days)
                        ->reject(fn (int $days): bool => in_array($days, $document->alerted ?? [], true))
                        ->filter(fn (int $days): bool => $daysLeft <= $days)->max();

                    if ($threshold === null) {
                        $document->scheduleNextAlert()->save();

                        continue;
                    }

                    $recipients = $access->usersWith(HrFeature::Documents->key('view'), $document->employee->branch_id)->pluck('id');

                    if ($document->employee->user_id) {
                        $recipients->push($document->employee->user_id);
                    }

                    foreach ($recipients->unique() as $userId) {
                        $accounts->notify($userId, new DocumentExpiring($document, $daysLeft));
                        $sent++;
                    }

                    DocumentRenewal::openFor($document);
                    app(EmployeeMessenger::class)->send($document->employee, 'hr_document_expiring', ['document' => $document->type->localized('name'), 'date' => $document->expires_on->toDateString(), 'days' => max(0, $daysLeft)], "hr.document:{$document->id}");
                    $document->alerted = [...($document->alerted ?? []), $threshold];
                    $document->scheduleNextAlert()->save();
                }
            });
        }

        $this->info("{$sent} alert(s) sent.");

        return self::SUCCESS;
    }
}
