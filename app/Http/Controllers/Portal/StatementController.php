<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\InvoiceResource;
use App\Models\Invoice;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One statement across Build (project invoices) and Business (suite subscriptions): what is
 * due, what is overdue, what was paid this year, and which subscriptions renew next.
 */
class StatementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $clientId = $request->user()->client_id ?? 0;
        $invoices = Invoice::query()->where('client_id', $clientId)->issued()->with('project')->latest('issue_date')->get();

        return Inertia::render('Portal/Statement', [
            'invoices' => InvoiceResource::collection($invoices),
            'totals' => [
                'due' => $this->sum($invoices->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])),
                'overdue' => $this->sum($invoices->where('status', InvoiceStatus::Overdue)),
                'paid_this_year' => $this->sum($invoices->where('status', InvoiceStatus::Paid)->filter(fn (Invoice $invoice) => $invoice->paid_at?->isCurrentYear())),
            ],
            'subscriptions' => Subscription::forClient($clientId)->orderByDesc('starts_on')->get()->map(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'module' => $subscription->module->label(),
                'quantity' => $subscription->quantity,
                'unit' => $subscription->billing_unit->label(),
                'unit_price' => (string) $subscription->unit_price,
                'total' => (string) bcmul((string) $subscription->quantity, (string) $subscription->unit_price, 2),
                'status' => $subscription->status->value,
                'starts_on' => $subscription->starts_on->toDateString(),
                'ends_on' => $subscription->ends_on?->toDateString(),
                'days_left' => $subscription->ends_on ? max(0, (int) today()->diffInDays($subscription->ends_on, false)) : null,
            ])->values()->all(),
            'copy' => ['portal' => trans('portal'), 'suite' => ['subscription_status' => trans('suite.subscription_status')]],
        ]);
    }

    /**
     * Exact decimal total of a set of invoices.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    private function sum($invoices): string
    {
        return $invoices->reduce(fn (string $carry, Invoice $invoice): string => bcadd($carry, (string) $invoice->total, 2), '0.00');
    }
}
