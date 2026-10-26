<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Platform\Ports\Audit;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Support\BookingMessages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class ChangeBookingStatus
{
    public function __construct(private Audit $audit) {}

    /**
     * Confirm, check in, cancel or mark a no-show, following BookingStatus::next(). Completing
     * goes through checkout. A cancelled or no-show booking frees its slot.
     */
    public function handle(Booking $booking, BookingStatus $to, ?string $reason = null, ?Authenticatable $actor = null): Booking
    {
        if (! in_array($to, $booking->status->next(), true)) {
            throw ValidationException::withMessages(['status' => __('reservations.errors.transition', ['from' => $booking->status->label(), 'to' => $to->label()])]);
        }

        $from = $booking->status;
        $booking->forceFill(['status' => $to->value, 'cancel_reason' => $to === BookingStatus::Cancelled ? $reason : $booking->cancel_reason])->save();
        $this->audit->record($booking, 'status_changed', ['from' => $from->value, 'to' => $to->value, 'reason' => $reason], $actor);

        if ($to === BookingStatus::Confirmed) {
            app(BookingMessages::class)->confirmed($booking);
        }

        return $booking;
    }

    /** The deposit of an online booking arrived: note it and confirm the booking. */
    public function depositReceived(Booking $booking, ?Authenticatable $actor = null): Booking
    {
        if ($booking->deposit_paid_at !== null || (float) $booking->deposit <= 0) {
            throw ValidationException::withMessages(['deposit' => __('reservations.errors.no_deposit_due')]);
        }

        $booking->forceFill(['deposit_paid_at' => now()])->save();
        $this->audit->record($booking, 'deposit_received', ['amount' => (string) $booking->deposit], $actor);

        return $booking->status === BookingStatus::Pending ? $this->handle($booking, BookingStatus::Confirmed, actor: $actor) : $booking;
    }
}
