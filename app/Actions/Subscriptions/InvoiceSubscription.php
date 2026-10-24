<?php

namespace App\Actions\Subscriptions;

use App\Actions\Invoices\SaveInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Modules\Platform\Models\Subscription;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

class InvoiceSubscription
{
    public function __construct(private SaveInvoice $saveInvoice) {}

    /**
     * A draft invoice for a subscription period: quantity × unit price,
     * e.g. "HR — 50 seat · 2026-11-01 → 2027-10-31".
     */
    public function period(Client $client, Subscription $subscription): Invoice
    {
        return $this->saveInvoice->handle(new Invoice, [
            'client_id' => $client->id, 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(14)->toDateString(),
        ], [[
            'description' => $this->describe($subscription, $subscription->quantity, $subscription->starts_on, $subscription->ends_on),
            'quantity' => $subscription->quantity,
            'unit_price' => (string) $subscription->unit_price,
        ]]);
    }

    /**
     * A prorated draft when seats (registers, branches) are added mid-term: the extra units for
     * the days left until the end of the period. Nothing when the period is open-ended.
     */
    public function increase(Client $client, Subscription $subscription, int $previousQuantity, ?Carbon $from = null): ?Invoice
    {
        $added = $subscription->quantity - $previousQuantity;

        if ($added <= 0 || $subscription->ends_on === null) {
            return null;
        }

        $from ??= today();
        $totalDays = max(1, (int) $subscription->starts_on->diffInDays($subscription->ends_on) + 1);
        $daysLeft = max(0, (int) $from->diffInDays($subscription->ends_on) + 1);
        $unitPrice = BigDecimal::of((string) $subscription->unit_price)->multipliedBy($daysLeft)->dividedBy($totalDays, 2, RoundingMode::HalfUp);

        if ($unitPrice->isZero()) {
            return null;
        }

        return $this->saveInvoice->handle(new Invoice, [
            'client_id' => $client->id, 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(14)->toDateString(),
        ], [[
            'description' => $this->describe($subscription, $added, $from, $subscription->ends_on, prorated: true),
            'quantity' => $added,
            'unit_price' => (string) $unitPrice,
        ]]);
    }

    private function describe(Subscription $subscription, int $quantity, Carbon $from, ?Carbon $to, bool $prorated = false): string
    {
        $period = $from->toDateString().($to ? ' → '.$to->toDateString() : '');
        $suffix = $prorated ? ' ('.__('admin.clients.suite.prorated').')' : '';

        return "{$subscription->module->label()} — {$quantity} {$subscription->billing_unit->label()} · {$period}{$suffix}";
    }
}
