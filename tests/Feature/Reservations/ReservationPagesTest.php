<?php

namespace Tests\Feature\Reservations;

use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class ReservationPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-01 08:00:00'); // Sunday 11:00 in Riyadh
        Subscription::factory()->module(Module::Reservations)->create(['client_id' => $this->client->id, 'quantity' => 1]);
    }

    private function url(string $path): string
    {
        return '/suite/all/reservations/'.ltrim($path, '/');
    }

    public function test_the_owner_sets_up_services_resources_and_hours(): void
    {
        $employee = $this->employeeAt($this->headOffice);
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();

        $this->actingAs($this->owner)->post($this->url('catalog/resources'), ['branch_id' => $this->headOffice->id, 'name_ar' => 'ملعب 1', 'name_en' => 'Court 1', 'kind' => 'court', 'capacity' => 4, 'is_active' => true])->assertSessionHasNoErrors();
        $court = Resource::query()->sole();

        $this->actingAs($this->owner)->post($this->url('catalog/services'), [
            'company_id' => $this->company->id, 'name_ar' => 'ساعة بادل', 'name_en' => 'Padel hour', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'price' => '200', 'vat_rate' => '15', 'deposit' => '50',
            'needs_staff' => true, 'is_online' => true, 'is_active' => true, 'resource_ids' => [$court->id], 'employee_ids' => [$employee->id],
        ])->assertSessionHasNoErrors();
        $service = Service::query()->sole();
        $this->assertSame([$employee->id], $service->staffIds());

        $this->actingAs($this->owner)->put($this->url("catalog/branches/{$this->headOffice->id}"), ['hours' => [0 => [['16:00', '15:00']]], 'slot_minutes' => 60, 'min_notice_minutes' => 0, 'max_days_ahead' => 14, 'is_online' => true])->assertSessionHasErrors('hours');
        $this->actingAs($this->owner)->put($this->url("catalog/branches/{$this->headOffice->id}"), [
            'hours' => [0 => [['16:00', '23:00']], 5 => []], 'slot_minutes' => 60, 'min_notice_minutes' => 0, 'max_days_ahead' => 14, 'is_online' => true,
        ])->assertSessionHasNoErrors();
        $settings = BranchSettings::query()->findOrFail($this->headOffice->id);
        $this->assertSame([['16:00', '23:00']], $settings->hours['0']);
        $this->assertSame([], $settings->hours['1']);

        $this->actingAs($this->owner)->get($this->url('catalog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Catalog')->has('services', 1)->has('resources', 1)->has('settings', 3)
            ->where('settings.0.booking_url', route('book.show', $settings->booking_token)));

        $this->actingAs($this->owner)->get($this->url("calendar?branch={$this->headOffice->id}&date=2026-11-01&slot_service={$service->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')->where('branch_id', $this->headOffice->id)->has('slots', 7)->where('slots.0.time', '16:00'));
    }

    public function test_staff_book_check_in_and_check_out_from_the_calendar(): void
    {
        $service = $this->service();

        $this->actingAs($this->owner)->post($this->url('bookings'), ['branch_id' => $this->headOffice->id, 'service_id' => $service->id, 'starts_at' => '2026-11-01T13:00:00+03:00', 'customer_name' => 'Noura', 'customer_phone' => '0555555555'])->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame(0.0, (float) $booking->deposit); // deposits are for online bookings

        $this->actingAs($this->owner)->get($this->url("calendar?branch={$this->headOffice->id}&date=2026-11-01"))->assertInertia(fn (Assert $page) => $page
            ->has('bookings', 1)->where('bookings.0.start', '13:00')->where('bookings.0.next', ['checked_in', 'cancelled', 'no_show']));

        $this->actingAs($this->owner)->put($this->url("bookings/{$booking->id}/status"), ['status' => 'checked_in'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url("bookings/{$booking->id}/checkout"), ['payment_means' => '10'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status->value);
        $this->assertNull($booking->fresh()->invoice_id); // no Invoicing subscription

        $this->actingAs($this->owner)->get($this->url('bookings?status=completed'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Reservations/Bookings')->has('bookings.data', 1));
    }

    public function test_a_branch_member_works_only_in_their_branch_and_cannot_cancel_without_the_ability(): void
    {
        $service = $this->service();
        $member = $this->memberWith(['reservations.bookings.view', 'reservations.bookings.create', 'reservations.bookings.update'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($member)->post($this->url('bookings'), ['branch_id' => $this->headOffice->id, 'service_id' => $service->id, 'starts_at' => '2026-11-01T13:00:00+03:00', 'customer_name' => 'X', 'customer_phone' => '0500000000'])->assertForbidden();
        $this->actingAs($member)->get($this->url('calendar'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('branch_id', $this->branch('RUH')->id)->has('branches', 1));

        $this->actingAs($this->owner)->post($this->url('bookings'), ['branch_id' => $this->headOffice->id, 'service_id' => $service->id, 'starts_at' => '2026-11-01T13:00:00+03:00', 'customer_name' => 'Y', 'customer_phone' => '0500000000']);
        $booking = Booking::query()->sole();
        $this->actingAs($member)->put($this->url("bookings/{$booking->id}/status"), ['status' => 'checked_in'])->assertForbidden(); // other branch

        $ruhMember = $this->memberWith(['reservations.bookings.view', 'reservations.bookings.update'], ScopeLevel::Company, $this->company);
        $this->actingAs($ruhMember)->put($this->url("bookings/{$booking->id}/status"), ['status' => 'cancelled'])->assertForbidden(); // needs the cancel ability
        $this->actingAs($ruhMember)->put($this->url("bookings/{$booking->id}/status"), ['status' => 'no_show'])->assertSessionHasNoErrors();
    }

    public function test_customers_book_through_the_public_link_only_when_online_booking_is_on(): void
    {
        $service = $this->service(['deposit' => '25.00']);
        $settings = BranchSettings::forBranch($this->headOffice->id, $this->client->id);
        app(CurrentTenant::class)->clear();

        $this->get("/book/{$settings->booking_token}")->assertNotFound();

        $settings->forceFill(['is_online' => true])->save();
        $this->get("/book/{$settings->booking_token}?service_id={$service->id}&date=2026-11-01")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Book')->has('services', 1)->where('slots.0.time', '12:00')->missing('slots.0.resource_ids'));

        $this->post("/book/{$settings->booking_token}", ['service_id' => $service->id, 'starts_at' => '2026-11-01T12:00:00+03:00', 'customer_name' => 'Sara', 'customer_phone' => 'not a phone'])->assertSessionHasErrors('customer_phone');
        $this->post("/book/{$settings->booking_token}", ['service_id' => $service->id, 'starts_at' => '2026-11-01T12:00:00+03:00', 'customer_name' => 'Sara', 'customer_phone' => '+966 500000000'])
            ->assertSessionHasNoErrors()->assertInertiaFlash('booking.status', 'pending');

        $this->asTenant();
        $booking = Booking::query()->sole();
        $this->assertSame('online', $booking->channel->value);
        $this->assertSame('25.00', (string) $booking->deposit);
    }

    private function service(array $attributes = []): Service
    {
        $service = (new Service(['company_id' => $this->company->id, 'name_ar' => 'استشارة', 'name_en' => 'Consultation', 'duration_minutes' => 30, 'price' => '100.00', ...$attributes]))->forceFill(['client_id' => $this->client->id]);
        $service->save();

        return $service;
    }
}
