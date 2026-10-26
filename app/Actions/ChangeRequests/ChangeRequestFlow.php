<?php

namespace App\Actions\ChangeRequests;

use App\Actions\Invoices\SaveInvoice;
use App\Enums\ChangeRequestStatus;
use App\Enums\Permission;
use App\Models\ChangeRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ChangeRequestUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A client asks for a change, the team answers with a quote, the client approves or rejects it
 * (B5). Approval drafts the invoice for the quoted amount, for the team to review and send.
 */
class ChangeRequestFlow
{
    public function __construct(private SaveInvoice $saveInvoice) {}

    public function submit(Project $project, User $client, string $title, string $description): ChangeRequest
    {
        $changeRequest = $project->changeRequests()->make(['title' => $title, 'description' => $description]);
        $changeRequest->forceFill(['user_id' => $client->id])->save();

        Notification::send(User::teamHolding(Permission::ProjectsManage), new ChangeRequestUpdated($changeRequest));

        return $changeRequest;
    }

    public function quote(ChangeRequest $changeRequest, string $amount, ?int $days, ?string $note): ChangeRequest
    {
        $this->expect($changeRequest, ChangeRequestStatus::Submitted, ChangeRequestStatus::Quoted);

        $changeRequest->forceFill([
            'status' => ChangeRequestStatus::Quoted, 'quote_amount' => $amount, 'quote_days' => $days,
            'quote_note' => $note, 'quoted_at' => now(),
        ])->save();

        Notification::send(User::clientAccountsOf($changeRequest->project->client_id), new ChangeRequestUpdated($changeRequest));

        return $changeRequest;
    }

    public function decide(ChangeRequest $changeRequest, User $client, bool $approve): ChangeRequest
    {
        $this->expect($changeRequest, ChangeRequestStatus::Quoted);

        DB::transaction(function () use ($changeRequest, $client, $approve): void {
            $changeRequest->forceFill([
                'status' => $approve ? ChangeRequestStatus::Approved : ChangeRequestStatus::Rejected,
                'decided_by' => $client->id, 'decided_at' => now(),
            ]);

            if ($approve && (float) $changeRequest->quote_amount > 0) {
                $project = $changeRequest->project;
                $invoice = $this->saveInvoice->handle(new Invoice, [
                    'client_id' => $project->client_id, 'project_id' => $project->id,
                    'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(14)->toDateString(),
                    'notes' => $changeRequest->title,
                ], [['description' => $changeRequest->title, 'quantity' => 1, 'unit_price' => (string) $changeRequest->quote_amount]]);
                $changeRequest->invoice_id = $invoice->id;
            }

            $changeRequest->save();
        });

        Notification::send(User::teamHolding(Permission::ProjectsManage), new ChangeRequestUpdated($changeRequest));

        return $changeRequest;
    }

    public function withdraw(ChangeRequest $changeRequest): ChangeRequest
    {
        $this->expect($changeRequest, ChangeRequestStatus::Submitted, ChangeRequestStatus::Quoted);
        $changeRequest->forceFill(['status' => ChangeRequestStatus::Withdrawn])->save();

        return $changeRequest;
    }

    private function expect(ChangeRequest $changeRequest, ChangeRequestStatus ...$statuses): void
    {
        if (! in_array($changeRequest->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => __('portal.changes.not_allowed')]);
        }
    }
}
