<?php

namespace Tests\Feature\Reservations;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Message;
use App\Modules\Platform\Models\Payment;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Ports\Payments;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\ChangeBookingStatus;
use App\Modules\Reservations\Actions\CheckoutBooking;
use App\Modules\Reservations\Actions\FindSlots;
use App\Modules\Reservations\Actions\SaveService;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Events\BookingCompleted;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\Concerns\FakesZatca;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use BuildsSuite, FakesZatca, RefreshDatabase;

    private Service $haircut;

    private Employee $barber;

    private const DAY = '2026-11-01'; // a Sunday

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 08:00:00'); // 11:00 in Riyadh
        Subscription::factory()->module(Module::Reservations)->create(['client_id' => $this->client->id, 'quantity' => 1]);

        $this->barber = $this->placedEmployee($this->headOffice);
        $chair = $this->resource($this->headOffice, 'كرسي 1');
        $this->haircut = app(SaveService::class)->handle(new Service, [
            'company_id' => $this->company->id, 'name_ar' => 'حلاقة', 'name_en' => 'Haircut', 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '50.00', 'needs_staff' => true,
        ], [$chair->id], [$this->barber->id]);
    }

    private function placedEmployee(Branch $branch): Employee
    {
        $employee = $this->employeeAt($branch);
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();

        return $employee;
    }

    private function resource(Branch $branch, string $name): Resource
    {
        $resource = (new Resource(['name_ar' => $name, 'name_en' => 'Chair', 'kind' => 'chair']))->forceFill(['client_id' => $this->client->id, 'company_id' => $branch->company_id, 'branch_id' => $branch->id]);
        $resource->save();

        return $resource;
    }

    /** @return list<string> */
    private function times(bool $public = false): array
    {
        return array_column(app(FindSlots::class)->handle($this->haircut->fresh(), $this->headOffice->id, self::DAY, $public), 'time');
    }

    private function book(string $time, BookingChannel $channel = BookingChannel::Staff, array $data = []): Booking
    {
        return app(BookAppointment::class)->handle($this->haircut->fresh(), $this->headOffice->id, self::DAY."T{$time}:00+03:00", ['customer_name' => 'Fahad', 'customer_phone' => '0500000000', ...$data], $channel);
    }

    public function test_slots_follow_opening_hours_notice_and_duration(): void
    {
        $staff = $this->times();
        $public = $this->times(public: true);

        $this->assertSame('10:30', $staff[0]);   // staff may book the slot that just started
        $this->assertSame('12:00', $public[0]);  // online customers need an hour's notice
        $this->assertSame('21:30', end($public)); // the last 30 minutes end at closing (22:00)
        $this->assertSame([], app(FindSlots::class)->handle($this->haircut, $this->headOffice->id, '2026-10-31'));  // the past
        $this->assertSame([], app(FindSlots::class)->handle($this->haircut, $this->branch('RUH')->id, self::DAY));  // no chair there
    }

    public function test_a_booking_holds_the_chair_and_barber_including_the_buffer(): void
    {
        $booking = $this->book('12:00');

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($this->barber->id, $booking->employee_id);
        $this->assertSame('2026-11-01 09:00:00', $booking->starts_at->utc()->toDateTimeString());
        $this->assertSame('2026-11-01 09:40:00', $booking->blocks_until->utc()->toDateTimeString());

        $times = $this->times();
        $this->assertNotContains('11:30', $times); // would run into 12:00
        $this->assertNotContains('12:00', $times);
        $this->assertNotContains('12:30', $times); // inside the 10 minute buffer
        $this->assertContains('13:00', $times);

        try {
            $this->book('12:00');
            $this->fail('Double booking accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starts_at', $e->errors());
        }

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, 'Customer called');
        $this->assertContains('12:00', $this->times());
    }

    public function test_staff_on_leave_or_working_elsewhere_are_not_offered(): void
    {
        $type = (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual']))->forceFill(['client_id' => $this->client->id]);
        $type->save();
        $leave = (new LeaveRequest(['starts_on' => self::DAY, 'ends_on' => self::DAY]))->forceFill(['client_id' => $this->client->id, 'employee_id' => $this->barber->id, 'leave_type_id' => $type->id, 'days' => 1, 'status' => 'approved']);
        $leave->save();

        $this->assertSame([], $this->times());

        $leave->forceFill(['status' => 'rejected'])->save();
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $this->barber->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('JED')->id, 'kind' => 'secondment', 'starts_on' => self::DAY, 'ends_on' => self::DAY])->save();
        $this->assertSame([], $this->times()); // seconded to Jeddah that day

        Subscription::query()->where('module', Module::Hr->value)->delete();
        $this->assertNotSame([], $this->times()); // without HR the staff requirement is not checked
    }

    public function test_an_online_booking_with_a_deposit_waits_then_checks_out_with_an_invoice(): void
    {
        Event::fake([BookingCompleted::class]);
        $this->haircut->forceFill(['deposit' => '20.00'])->save();
        Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        Company::query()->whereKey($this->company->id)->update(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'city' => 'Riyadh', 'postal_code' => '12211', 'building_number' => '1234', 'street' => 'X', 'district' => 'Y']);
        $this->readyUnit($this->company->id, $this->client->id);
        $this->fakeZatcaAccepting();

        try {
            $this->book('11:30', BookingChannel::Online);
            $this->fail('Online booking inside the notice period accepted');
        } catch (ValidationException) {
        }

        $booking = $this->book('15:00', BookingChannel::Online);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNotContains('15:00', $this->times()); // pending still holds the slot

        try {
            app(CheckoutBooking::class)->handle($booking, PaymentMeans::Cash);
            $this->fail('Pending booking checked out');
        } catch (ValidationException) {
        }

        $booking = app(ChangeBookingStatus::class)->depositReceived($booking);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $booking = app(ChangeBookingStatus::class)->handle($booking, BookingStatus::CheckedIn);

        $booking = app(CheckoutBooking::class)->handle($booking, PaymentMeans::Card, $this->owner);
        $this->assertSame(BookingStatus::Completed, $booking->status);
        $invoice = Invoice::query()->findOrFail($booking->invoice_id);
        $this->assertSame('50.00', $invoice->payableAmount());
        $this->assertSame('48', $invoice->payment_means);
        Event::assertDispatched(BookingCompleted::class, fn (BookingCompleted $event) => $event->employeeId === $this->barber->id && $event->total === '50.00');
    }

    public function test_whatsapp_confirms_bookings_and_reminds_the_day_before_once(): void
    {
        $this->book('15:00');
        $this->assertSame(['res_booking_confirmed'], Message::query()->pluck('template')->all());
        $this->assertSame('966500000000', Message::query()->value('to'));

        $this->travelTo('2026-11-01 08:00:00');
        $this->artisan('reservations:send-reminders')->assertSuccessful();
        $this->artisan('reservations:send-reminders')->assertSuccessful();
        $this->assertSame(1, Message::query()->where('template', 'res_booking_reminder')->count());
    }

    public function test_an_online_deposit_paid_on_the_payment_page_confirms_the_booking(): void
    {
        $this->haircut->forceFill(['deposit' => '20.00'])->save();
        $booking = $this->book('15:00', BookingChannel::Online);
        $link = app(Payments::class)->link("reservations.booking:{$booking->id}", '20.00', 'Deposit', null, $this->client->id);

        $this->post('/pay/'.Payment::query()->findOrFail($link->id)->token);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->deposit_paid_at);
    }
}
