<?php

namespace Tests\Feature\Suite;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Platform\Adapters\WhatsAppMessaging;
use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Models\Message;
use App\Modules\Platform\Models\Payment;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Ports\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saudi_mobile_numbers_are_normalized(): void
    {
        $this->assertSame('966501234567', WhatsAppMessaging::normalize('0501234567'));
        $this->assertSame('966501234567', WhatsAppMessaging::normalize('+966 50 123 4567'));
        $this->assertSame('966501234567', WhatsAppMessaging::normalize('501234567'));
        $this->assertNull(WhatsAppMessaging::normalize(null));
    }

    public function test_messages_are_recorded_and_sent_through_the_whatsapp_service_when_configured(): void
    {
        $this->assertNull(app(Messaging::class)->send(null, 'hr_payslip_ready', ['name' => 'Sara', 'month' => '2026-11', 'amount' => '5000']));

        $logged = app(Messaging::class)->send('0501234567', 'hr_payslip_ready', ['name' => 'Sara', 'month' => '2026-11', 'amount' => '5000'], 'en');
        $this->assertSame('logged', $logged->status);
        $this->assertStringContainsString('5000', $logged->body);

        config(['baydaq.messaging.driver' => 'whatsapp', 'baydaq.messaging.whatsapp.url' => 'https://wa.example.test', 'baydaq.messaging.whatsapp.token' => 'secret']);
        Http::fake(['wa.example.test/*' => Http::response(['id' => 'wamid.1'])]);

        $sent = app(Messaging::class)->send('0501234567', 'hr_payslip_ready', ['name' => 'Sara', 'month' => '2026-11', 'amount' => '5000']);
        $this->assertSame('sent', $sent->status);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret') && $request['to'] === '966501234567' && $request['template'] === 'hr_payslip_ready');
        $this->assertSame('wamid.1', Message::query()->latest('id')->value('provider_id'));
    }

    public function test_a_fake_payment_link_is_paid_once_and_announced(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $link = app(Payments::class)->link('test.thing:1', '115.00', 'Test', '/done');

        $this->get($link->url)->assertOk()->assertSee('115.00');
        $token = Payment::query()->value('token');
        $this->post("/pay/{$token}")->assertRedirect(route('payments.return', $token));
        $this->get(route('payments.return', $token))->assertRedirect('/done');
        $this->post("/pay/{$token}")->assertNotFound(); // already paid

        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
        $this->assertSame('paid', Payment::query()->value('status'));
        $this->assertNull(app(Payments::class)->openLinkFor('test.thing:1'));
    }

    public function test_moyasar_links_and_signed_webhooks(): void
    {
        config(['baydaq.payments.driver' => 'moyasar', 'baydaq.payments.moyasar.secret_key' => 'sk_test', 'baydaq.payments.moyasar.webhook_secret' => 'whsec']);
        Http::fake(['api.moyasar.com/v1/invoices' => Http::response(['id' => 'inv_123', 'url' => 'https://checkout.moyasar.com/invoices/inv_123'])]);
        Event::fake([PaymentSucceeded::class]);

        $link = app(Payments::class)->link('test.thing:2', '57.50', 'Booking deposit');
        $this->assertSame('https://checkout.moyasar.com/invoices/inv_123', $link->url);
        Http::assertSent(fn ($request) => $request['amount'] === 5750 && $request['metadata']['reference'] === 'test.thing:2');

        $this->get('/pay/'.Payment::query()->value('token'))->assertNotFound(); // no test page with a real provider

        $paid = ['type' => 'payment_paid', 'data' => ['id' => 'pay_1', 'invoice_id' => 'inv_123', 'status' => 'paid']];
        $this->postJson('/payments/webhook/moyasar', [...$paid, 'secret_token' => 'wrong'])->assertForbidden();
        $this->postJson('/payments/webhook/moyasar', [...$paid, 'secret_token' => 'whsec'])->assertOk();

        Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->reference === 'test.thing:2' && $event->amount === '57.50');
    }

    public function test_a_client_pays_a_baydaq_invoice_from_the_portal(): void
    {
        $this->withoutVite();
        $client = Client::factory()->create();
        $user = User::factory()->client($client)->create();
        $invoice = Invoice::factory()->for($client)->sent()->create(['total' => '230.00']);
        $other = Invoice::factory()->sent()->create();

        $this->actingAs($user)->post(route('portal.invoices.pay', $other))->assertForbidden();
        $this->actingAs($user)->post(route('portal.invoices.pay', $invoice))->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertSame('core.invoice:'.$invoice->id, $payment->reference);
        $this->assertSame('230.00', (string) $payment->amount);

        $this->post("/pay/{$payment->token}");
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }
}
