<?php

namespace App\Modules\Reservations\Adapters;

use App\Modules\Platform\Ports\CustomerData;
use App\Modules\Reservations\Models\Booking;

class BookingCustomerData implements CustomerData
{
    public function export(int $customerId): array
    {
        return ['bookings' => Booking::query()->where('platform_customer_id', $customerId)->latest('starts_at')->get()
            ->map(fn (Booking $booking): array => [
                'reference' => $booking->reference, 'starts_at' => $booking->starts_at->toIso8601String(), 'name' => $booking->customer_name,
                'phone' => $booking->customer_phone, 'email' => $booking->customer_email, 'status' => $booking->status->value,
            ])->all()];
    }

    public function erase(int $customerId): int
    {
        return Booking::query()->where('platform_customer_id', $customerId)->update(['customer_name' => '—', 'customer_phone' => '', 'customer_email' => null]);
    }
}
