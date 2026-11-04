<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Subscriptions\InvoiceSubscription;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSubscriptionRequest;
use App\Models\Client;
use App\Modules\Platform\Actions\Subscriptions\SaveSubscription;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Http\RedirectResponse;

/**
 * Baydaq staff record which suite modules a client bought and how many seats, registers or branches.
 */
class ClientSubscriptionController extends Controller
{
    use RendersAdminPages;

    public function store(SaveSubscriptionRequest $request, Client $client, SaveSubscription $saveSubscription): RedirectResponse
    {
        $saveSubscription->handle($client, new Subscription, $request->subscriptionData());
        $this->toast(__('admin.clients.suite.subscription_saved'));

        return back();
    }

    /**
     * Raising the quantity on a running subscription also drafts a prorated invoice for the extra units.
     */
    public function update(SaveSubscriptionRequest $request, Client $client, int $subscription, SaveSubscription $saveSubscription, InvoiceSubscription $invoiceSubscription): RedirectResponse
    {
        $model = Subscription::forClient($client)->findOrFail($subscription);
        $previous = $model->quantity;

        $saved = $saveSubscription->handle($client, $model, $request->subscriptionData());
        $invoice = $saved->isActive() ? $invoiceSubscription->increase($client, $saved, $previous) : null;

        $this->toast($invoice ? __('admin.clients.suite.prorated_invoice', ['number' => $invoice->number]) : __('admin.clients.suite.subscription_saved'));

        return back();
    }

    /**
     * A draft invoice for the whole subscription period (first sale or renewal).
     */
    public function invoice(Client $client, int $subscription, InvoiceSubscription $invoiceSubscription): RedirectResponse
    {
        $invoice = $invoiceSubscription->period($client, Subscription::forClient($client)->findOrFail($subscription));
        $this->toast(__('admin.clients.suite.invoice_created', ['number' => $invoice->number]));

        return to_route('admin.invoices.edit', $invoice);
    }
}
