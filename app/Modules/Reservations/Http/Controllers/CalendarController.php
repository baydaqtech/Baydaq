<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\ChangeBookingStatus;
use App\Modules\Reservations\Actions\CheckoutBooking;
use App\Modules\Reservations\Actions\FindSlots;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Enums\ReservationsFeature;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * A branch's day: bookings by resource, a new-booking form fed by free slots, and the actions
 * on each booking (confirm, check in, deposit, checkout, cancel, no-show).
 */
class CalendarController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function show(Request $request, OrgDirectory $directory, StaffDirectory $staff, FindSlots $slots): Response
    {
        $branches = $this->branchesFor(ReservationsFeature::Bookings, FeatureAction::View);
        $branch = $branches->first(fn (BranchRef $branch): bool => $branch->id === (int) $request->query('branch')) ?? $branches->first();
        $timezone = $branch ? ($directory->company($branch->companyId)?->timezone ?? 'Asia/Riyadh') : 'Asia/Riyadh';
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date'), $timezone), null, false) ?: CarbonImmutable::now($timezone)->startOfDay();

        $bookings = $branch
            ? Booking::query()->where('branch_id', $branch->id)->whereBetween('starts_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()])->with(['service', 'resource'])->orderBy('starts_at')->get()
            : collect();
        $staffAt = $branch ? collect($staff->staffAt($branch->id))->mapWithKeys(fn ($employee): array => [$employee->id => $employee->name]) : collect();
        $staffName = fn (?int $id): ?string => $id === null ? null : ($staffAt[$id] ?? $staff->employee($id)?->name);
        $service = $branch && $request->filled('slot_service') ? Service::query()->where('company_id', $branch->companyId)->find($request->integer('slot_service')) : null;

        return $this->suitePage('Reservations/Calendar', [
            'branches' => $branches->map(fn (BranchRef $item): array => ['value' => $item->id, 'label' => $item->name])->all(),
            'branch_id' => $branch?->id,
            'date' => $date->toDateString(),
            'resources' => $branch ? Resource::query()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Resource $resource): array => ['id' => $resource->id, 'name' => $resource->localized('name'), 'kind' => $resource->kind->value, 'capacity' => $resource->capacity])->all() : [],
            'services' => $branch ? Service::query()->where('company_id', $branch->companyId)->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Service $item): array => ['value' => $item->id, 'label' => $item->localized('name'), 'duration_minutes' => $item->duration_minutes, 'price' => (string) $item->price, 'color' => $item->color])->all() : [],
            'staff' => $staffAt->map(fn (string $name, int $id): array => ['value' => $id, 'label' => $name])->values()->all(),
            'bookings' => $bookings->map(fn (Booking $booking): array => self::bookingArray($booking, $timezone, $staffName))->all(),
            'slots' => $service ? $slots->handle($service, $branch->id, $date->toDateString(), false, max(1, $request->integer('party_size', 1))) : null,
            'statuses' => BookingStatus::options(),
            'payment_means' => PaymentMeans::options(),
        ], ['reservations']);
    }

    public function store(Request $request, BookAppointment $book): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'], 'service_id' => ['required', 'integer'], 'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'], 'customer_phone' => ['required', 'string', 'max:30'], 'customer_email' => ['nullable', 'email', 'max:255'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:500'], 'note' => ['nullable', 'string', 'max:255'],
            'resource_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'], 'nights' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $this->authorizeBranchFor(ReservationsFeature::Bookings, FeatureAction::Create, (int) $data['branch_id']);
        $service = Service::query()->findOrFail($data['service_id']);

        $booking = $book->handle($service, (int) $data['branch_id'], $data['starts_at'], collect($data)->except(['branch_id', 'service_id', 'starts_at'])->all(), BookingChannel::Staff, $request->user());
        $this->toast(__('reservations.calendar.booked', ['reference' => $booking->reference]));

        return back();
    }

    public function status(Request $request, Booking $booking, ChangeBookingStatus $change): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value, BookingStatus::Cancelled->value, BookingStatus::NoShow->value])], 'reason' => ['nullable', 'string', 'max:255']]);
        $status = BookingStatus::from($data['status']);
        $key = $status === BookingStatus::Cancelled ? ReservationsFeature::Bookings->key('cancel') : ReservationsFeature::Bookings->key(FeatureAction::Update);
        abort_unless($this->access()->allowsBranch($key, $booking->branch_id, $booking->company_id), 403);

        $change->handle($booking, $status, $data['reason'] ?? null, $request->user());
        $this->toast(__('reservations.calendar.updated'));

        return back();
    }

    public function deposit(Request $request, Booking $booking, ChangeBookingStatus $change): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(ReservationsFeature::Bookings->key(FeatureAction::Update), $booking->branch_id, $booking->company_id), 403);
        $change->depositReceived($booking, $request->user());
        $this->toast(__('reservations.calendar.updated'));

        return back();
    }

    public function checkout(Request $request, Booking $booking, CheckoutBooking $checkout): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(ReservationsFeature::Bookings->key('checkout'), $booking->branch_id, $booking->company_id), 403);
        $data = $request->validate(['payment_means' => ['required', Rule::enum(PaymentMeans::class)]]);

        $checkout->handle($booking, PaymentMeans::from($data['payment_means']), $request->user());
        $this->toast(__('reservations.calendar.checked_out', ['reference' => $booking->reference]));

        return back();
    }

    /**
     * @param  callable(?int): ?string  $staffName
     * @return array<string, mixed>
     */
    public static function bookingArray(Booking $booking, string $timezone, callable $staffName): array
    {
        return [
            'id' => $booking->id, 'branch_id' => $booking->branch_id, 'reference' => $booking->reference, 'status' => $booking->status->value, 'channel' => $booking->channel->value,
            'starts_at' => $booking->starts_at->setTimezone($timezone)->toIso8601String(), 'date' => $booking->starts_at->setTimezone($timezone)->toDateString(),
            'start' => $booking->starts_at->setTimezone($timezone)->format('H:i'), 'end' => $booking->ends_at->setTimezone($timezone)->format('H:i'),
            'service' => $booking->service?->localized('name'), 'color' => $booking->service?->color,
            'resource_id' => $booking->resource_id, 'resource' => $booking->resource?->localized('name'), 'employee_id' => $booking->employee_id, 'employee' => $staffName($booking->employee_id),
            'customer_name' => $booking->customer_name, 'customer_phone' => $booking->customer_phone, 'party_size' => $booking->party_size, 'note' => $booking->note,
            'price' => (string) $booking->price, 'deposit' => (string) $booking->deposit, 'deposit_paid' => $booking->deposit_paid_at !== null,
            'invoice_id' => $booking->invoice_id, 'next' => array_map(fn (BookingStatus $status): string => $status->value, $booking->status->next()),
        ];
    }
}
