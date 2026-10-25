<?php

namespace App\Modules\Reservations\Support;

use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Reservations\Models\Booking;

/**
 * WhatsApp to the customer about their booking (R3): when it is confirmed, and a reminder the
 * day before. Times are shown in the company's timezone.
 */
class BookingMessages
{
    public function __construct(private Messaging $messaging, private OrgDirectory $directory) {}

    public function confirmed(Booking $booking): void
    {
        $this->send($booking, 'res_booking_confirmed');
    }

    public function reminder(Booking $booking): void
    {
        $this->send($booking, 'res_booking_reminder');
        $booking->forceFill(['reminded_at' => now()])->save();
    }

    private function send(Booking $booking, string $template): void
    {
        $local = $booking->starts_at->copy()->setTimezone($this->directory->company($booking->company_id)?->timezone ?? 'Asia/Riyadh');

        $this->messaging->send($booking->customer_phone, $template, [
            'name' => $booking->customer_name, 'number' => $booking->reference, 'service' => $booking->service->localized('name'),
            'date' => $local->toDateString(), 'time' => $local->format('H:i'),
        ], app()->getLocale(), "reservations.booking:{$booking->id}");
    }
}
