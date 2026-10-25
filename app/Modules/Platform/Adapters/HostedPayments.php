<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\PaymentLink;
use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Models\Payment;
use App\Modules\Platform\Ports\Payments;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Payment links on a hosted page. Driver "moyasar" uses Moyasar invoices (mada, Apple Pay,
 * STC Pay, cards); driver "fake" serves a local test page instead, so everything works before
 * a merchant account exists.
 */
class HostedPayments implements Payments
{
    public function link(string $reference, string $amount, string $description, ?string $returnUrl = null, ?int $clientId = null): PaymentLink
    {
        $driver = (string) config('baydaq.payments.driver', 'fake');
        $payment = new Payment;
        $payment->forceFill([
            'client_id' => $clientId, 'token' => Str::random(40), 'reference' => $reference, 'amount' => (string) BigDecimal::of($amount)->toScale(2),
            'currency' => config('baydaq.currency', 'SAR'), 'description' => mb_substr($description, 0, 250), 'provider' => $driver, 'status' => 'pending', 'return_url' => $returnUrl,
        ])->save();

        if ($driver === 'moyasar') {
            $response = Http::withBasicAuth((string) config('baydaq.payments.moyasar.secret_key'), '')->acceptJson()->timeout(15)
                ->post(rtrim((string) config('baydaq.payments.moyasar.url'), '/').'/invoices', [
                    'amount' => (int) (string) BigDecimal::of($amount)->multipliedBy(100)->toScale(0),
                    'currency' => $payment->currency,
                    'description' => $payment->description,
                    'callback_url' => route('payments.webhook', 'moyasar'),
                    'success_url' => route('payments.return', $payment->token),
                    'back_url' => $returnUrl ?? url('/'),
                    'metadata' => ['reference' => $reference, 'token' => $payment->token],
                ]);

            if (! $response->successful()) {
                $payment->forceFill(['status' => 'failed'])->save();

                throw new RuntimeException('Payment provider refused the link: '.mb_substr($response->body(), 0, 200));
            }

            $payment->forceFill(['provider_id' => $response->json('id'), 'checkout_url' => $response->json('url')])->save();
        } else {
            $payment->forceFill(['checkout_url' => route('payments.fake', $payment->token)])->save();
        }

        return self::data($payment);
    }

    public function openLinkFor(string $reference): ?PaymentLink
    {
        $payment = Payment::query()->where('reference', $reference)->where('status', 'pending')->latest('id')->first();

        return $payment ? self::data($payment) : null;
    }

    /** Mark paid once and tell the owning module. */
    public static function markPaid(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === 'paid') {
                return;
            }

            $locked->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            PaymentSucceeded::dispatch($locked->id, $locked->reference, (string) $locked->amount, $locked->client_id);
        });
    }

    /** Ask Moyasar whether its invoice was paid (used when the payer comes back). */
    public static function confirmWithProvider(Payment $payment): bool
    {
        if ($payment->provider !== 'moyasar' || $payment->provider_id === null) {
            return false;
        }

        $response = Http::withBasicAuth((string) config('baydaq.payments.moyasar.secret_key'), '')->acceptJson()->timeout(15)
            ->get(rtrim((string) config('baydaq.payments.moyasar.url'), '/').'/invoices/'.$payment->provider_id);

        return $response->successful() && $response->json('status') === 'paid';
    }

    private static function data(Payment $payment): PaymentLink
    {
        return new PaymentLink($payment->id, $payment->reference, (string) $payment->amount, (string) $payment->checkout_url, $payment->status);
    }
}
