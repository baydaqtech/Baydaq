<?php

namespace App\Modules\Reservations\Adapters;

use App\Modules\Platform\Data\CustomerActivityItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\CustomerActivity;
use App\Modules\Reservations\Enums\ReservationsFeature;
use App\Modules\Reservations\Models\Booking;

class BookingCustomerActivity implements CustomerActivity
{
    public function activity(int $customerId, ScopeContext $context, int $limit = 20): array
    {
        return $context->reach(ReservationsFeature::Bookings->key(FeatureAction::View))
            ->apply(Booking::query()->where('platform_customer_id', $customerId))
            ->with('service')->latest('starts_at')->limit($limit)->get()
            ->map(fn (Booking $booking): CustomerActivityItem => new CustomerActivityItem(
                'reservations', $booking->service?->localized('name') ?? $booking->reference, $booking->status->label(), $booking->starts_at->toIso8601String(), (string) $booking->price, '/reservations/bookings',
            ))->all();
    }
}
