<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Adapters\HostedPayments;
use App\Modules\Platform\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where payers land: the provider's webhook, the way back from the payment page, and the local
 * test page used while no provider is configured.
 */
class PaymentController extends Controller
{
    /** Moyasar calls this on payment events; the shared secret proves it is them. */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        abort_unless($provider === 'moyasar' && config('baydaq.payments.driver') === 'moyasar', 404);
        $secret = (string) config('baydaq.payments.moyasar.webhook_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->input('secret_token')), 403);

        $invoiceId = $request->input('data.invoice_id') ?? $request->input('data.id');
        $payment = Payment::query()->where('provider', 'moyasar')->where('provider_id', $invoiceId)->first();

        if ($payment && $request->input('type') === 'payment_paid' && $request->input('data.status') === 'paid') {
            HostedPayments::markPaid($payment);
        }

        return response()->json(['ok' => true]);
    }

    /** Back from the payment page: confirm with the provider, then go where the payer started. */
    public function return(string $token): RedirectResponse
    {
        $payment = Payment::query()->where('token', $token)->firstOrFail();

        if ($payment->status === 'pending' && HostedPayments::confirmWithProvider($payment)) {
            HostedPayments::markPaid($payment);
        }

        return redirect()->to($payment->return_url ?? url('/'))->with('payment_status', $payment->fresh()->status);
    }

    /** Local test page standing in for the provider (driver "fake" only). */
    public function fake(string $token): View
    {
        abort_unless(config('baydaq.payments.driver', 'fake') === 'fake' && ! app()->isProduction(), 404);

        return view('payments.fake', ['payment' => Payment::query()->where('token', $token)->firstOrFail()]);
    }

    public function fakePay(string $token): RedirectResponse
    {
        abort_unless(config('baydaq.payments.driver', 'fake') === 'fake' && ! app()->isProduction(), 404);
        $payment = Payment::query()->where('token', $token)->where('status', 'pending')->firstOrFail();

        HostedPayments::markPaid($payment);

        return redirect()->route('payments.return', $token);
    }
}
