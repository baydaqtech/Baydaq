<?php

namespace Tests\Feature\Finance;

use App\Modules\Hr\Actions\Pay\DecideAdvance;
use App\Modules\Hr\Actions\Pay\RecordRepayment;
use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Enums\RepaymentMethod;
use App\Modules\Hr\Models\Advance;
use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Models\Customer as Buyer;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\ChangeBookingStatus;
use App\Modules\Reservations\Actions\CheckoutBooking;
use App\Modules\Reservations\Actions\SaveService;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsLedger;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Money that moves outside a sale or a bill still reaches the books: customer wallets, booking
 * deposits, advances and loans, the end-of-service award, and purchases between group companies.
 */
class LedgerCoverageTest extends TestCase
{
    use AssertsLedger, BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->asTenant();
    }

    public function test_wallet_top_ups_and_loyalty_credit_are_owed_to_customers(): void
    {
        Company::query()->whereKey($this->company->id)->update(['loyalty_percent' => 5]);
        $customer = app(Customers::class)->identify('Fahad', '0500000000');

        $this->actingAs($this->owner)->post($this->suiteUrl("customers/{$customer->id}/wallet"), [
            'kind' => 'top_up', 'amount' => '100', 'company_id' => $this->company->id, 'paid_with' => 'card',
        ])->assertSessionHasNoErrors();
        app(Customers::class)->reward($customer->id, $this->company->id, '200', 'pos.order', '9');

        $this->assertSame('100.00', $this->ledgerBalance($this->company->id, 'card_clearing'));
        $this->assertSame('10.00', $this->ledgerBalance($this->company->id, 'loyalty'));
        $this->assertSame('-110.00', $this->ledgerBalance($this->company->id, 'customer_wallets'));
    }

    public function test_a_booking_deposit_is_held_until_checkout_and_then_counts_as_the_sale(): void
    {
        $this->travelTo('2026-11-01 08:00:00');
        $room = (new Resource(['name_ar' => 'غرفة', 'name_en' => 'Room', 'kind' => 'room']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id]);
        $room->save();
        $service = app(SaveService::class)->handle(new Service, ['company_id' => $this->company->id, 'name_ar' => 'جلسة', 'name_en' => 'Session', 'duration_minutes' => 30, 'price' => '50.00'], [$room->id], []);
        $booking = app(BookAppointment::class)->handle($service, $this->headOffice->id, '2026-11-01T12:00:00+03:00', ['customer_name' => 'Fahad', 'customer_phone' => '0500000000'], BookingChannel::Staff);
        $booking->forceFill(['deposit' => '20.00', 'status' => 'pending'])->save();

        $booking = app(ChangeBookingStatus::class)->depositReceived($booking, PaymentMeans::Card);
        $this->assertSame('-20.00', $this->ledgerBalance($this->company->id, 'customer_deposits'));

        app(CheckoutBooking::class)->handle($booking, PaymentMeans::Cash);

        $this->assertPostedBalanced('reservations.booking', $booking->id);
        $this->assertSame('0.00', $this->ledgerBalance($this->company->id, 'customer_deposits'));
        $this->assertSame('20.00', $this->ledgerBalance($this->company->id, 'card_clearing'));
        $this->assertSame('30.00', $this->ledgerBalance($this->company->id, 'cash'));
        $this->assertSame('-43.48', $this->ledgerBalance($this->company->id, 'sales'));
        $this->assertSame('-6.52', $this->ledgerBalance($this->company->id, 'vat_output'));
    }

    public function test_an_advance_is_due_from_the_employee_until_it_is_repaid(): void
    {
        $advance = (new Advance)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $this->employeeAt()->id, 'amount' => '1000', 'installments' => 2,
            'first_installment_month' => today()->startOfMonth(), 'status' => AdvanceStatus::Approved->value,
        ]);
        $advance->save();

        $advance = app(DecideAdvance::class)->disburse($advance, 'cash', $this->owner);
        app(RecordRepayment::class)->handle($advance, '400', RepaymentMethod::Transfer, $this->owner);

        $this->assertSame('600.00', $this->ledgerBalance($this->company->id, 'employee_receivable'));
        $this->assertSame('-1000.00', $this->ledgerBalance($this->company->id, 'cash'));
        $this->assertSame('400.00', $this->ledgerBalance($this->company->id, 'bank'));
    }

    public function test_an_invoice_between_group_companies_is_the_buyers_purchase(): void
    {
        $buyer = app(SaveCompany::class)->handle($this->organization->companies()->make(), ['name_ar' => 'التجارة', 'name_en' => 'Trading', 'timezone' => 'Asia/Riyadh', 'status' => 'active']);
        $customer = (new Buyer(['name_ar' => 'التجارة', 'name_en' => 'Trading']))->forceFill(['client_id' => $this->client->id, 'company_id' => $buyer->id]);
        $customer->save();
        $invoice = (new Invoice)->forceFill([
            'id' => 77, 'company_id' => $this->company->id, 'number' => 'INV-2026-00077', 'kind' => 'invoice', 'intercompany' => true,
            'source' => 'hr.secondment', 'issue_date' => today(), 'vat' => '150.00', 'total' => '1150.00',
        ])->setRelation('customer', $customer);

        $posting = IssueInvoice::purchasePosting($invoice);

        $this->assertSame($buyer->id, $posting->companyId);
        $this->assertSame([
            ['account' => 'salaries', 'debit' => '1000.00'],
            ['account' => 'vat_input', 'debit' => '150.00', 'tax_base' => '1000.00'],
            ['account' => 'payables', 'credit' => '1150.00'],
        ], $posting->lines);
        $this->assertNull(IssueInvoice::purchasePosting($invoice->forceFill(['intercompany' => false])));
    }
}
