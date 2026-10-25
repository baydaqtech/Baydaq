<?php

namespace App\Modules\Reservations\Listeners;

use App\Modules\Platform\Events\PaymentSucceeded;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Actions\ChangeBookingStatus;
use App\Modules\Reservations\Models\Booking;

/** A booking deposit paid online confirms the booking. */
class ConfirmPaidDeposit
{
    public function __construct(private CurrentTenant $tenant, private ChangeBookingStatus $status) {}

    public function handle(PaymentSucceeded $event): void
    {
        if (! str_starts_with($event->reference, 'reservations.booking:') || $event->clientId === null) {
            return;
        }

        $this->tenant->runFor($event->clientId, function () use ($event): void {
            $booking = Booking::query()->find((int) substr($event->reference, strlen('reservations.booking:')));

            if ($booking && $booking->deposit_paid_at === null) {
                $this->status->depositReceived($booking);
            }
        });
    }
}
