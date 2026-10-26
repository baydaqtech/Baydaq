<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Ports\Payments;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\FindSlots;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A branch's public booking page. No login: the token in the link names the branch (and so the
 * tenant and company); the branch must have online booking switched on.
 */
class PublicBookingController extends Controller
{
    public function show(Request $request, string $token, CurrentTenant $tenant, OrgDirectory $directory, FindSlots $slots): Response
    {
        $settings = $this->settings($token);

        $props = $tenant->runFor($settings->client_id, function () use ($request, $settings, $directory, $slots): array {
            $branch = $directory->branch($settings->branch_id);
            $company = $directory->company($branch->companyId);
            $services = Service::query()->where('company_id', $branch->companyId)->where('is_active', true)->where('is_online', true)->orderBy('sort_order')->get();
            $chosen = $services->firstWhere('id', $request->integer('service_id'));
            $today = CarbonImmutable::now($company->timezone)->startOfDay();

            return [
                'place' => ['company' => $company->name, 'branch' => $branch->name],
                'services' => $services->map(fn (Service $service): array => [
                    'id' => $service->id, 'name' => $service->localized('name'), 'description' => $service->localized('description'),
                    'duration_minutes' => $service->duration_minutes, 'price' => (string) $service->price, 'deposit' => (string) $service->deposit, 'is_stay' => $service->is_stay,
                ])->all(),
                'window' => ['from' => $today->toDateString(), 'to' => $today->addDays($settings->max_days_ahead)->toDateString()],
                'slots' => $chosen && $request->filled('date')
                    ? array_map(fn (array $slot): array => ['starts_at' => $slot['starts_at'], 'time' => $slot['time']], $slots->handle($chosen, $settings->branch_id, (string) $request->query('date'), true, max(1, $request->integer('party_size', 1)), nights: max(1, $request->integer('nights', 1))))
                    : null,
            ];
        });

        return Inertia::render('Reservations/Book', [...$props, 'token' => $token, 'copy' => ['reservations' => ['book' => trans('reservations.book')]]]);
    }

    public function store(Request $request, string $token, CurrentTenant $tenant, BookAppointment $book): RedirectResponse
    {
        $settings = $this->settings($token);
        $data = $request->validate([
            'service_id' => ['required', 'integer'], 'starts_at' => ['required', 'date'], 'party_size' => ['nullable', 'integer', 'min:1', 'max:50'], 'nights' => ['nullable', 'integer', 'min:1', 'max:30'],
            'customer_name' => ['required', 'string', 'max:255'], 'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'], 'customer_email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = $tenant->runFor($settings->client_id, function () use ($settings, $data, $book): Booking {
            $service = Service::query()->where('is_online', true)->findOrFail($data['service_id']);

            return $book->handle($service, $settings->branch_id, $data['starts_at'], collect($data)->only(['customer_name', 'customer_phone', 'customer_email', 'party_size', 'nights', 'note'])->all(), BookingChannel::Online);
        });

        // A deposit is paid online straight away (the booking confirms itself when it arrives).
        $paymentUrl = $booking->status === BookingStatus::Pending && (float) $booking->deposit > 0
            ? app(Payments::class)->link("reservations.booking:{$booking->id}", (string) $booking->deposit, __('reservations.book.deposit_for', ['reference' => $booking->reference]), url("/book/{$token}"), $settings->client_id)->url
            : null;

        Inertia::flash('booking', ['reference' => $booking->reference, 'status' => $booking->status->value, 'deposit' => (string) $booking->deposit, 'payment_url' => $paymentUrl]);

        return back();
    }

    private function settings(string $token): BranchSettings
    {
        return BranchSettings::withoutGlobalScopes()->where('booking_token', $token)->where('is_online', true)->firstOrFail();
    }
}
