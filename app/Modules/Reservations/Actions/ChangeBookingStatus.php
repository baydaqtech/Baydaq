<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Support\BookingMessages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeBookingStatus
{
    public function __construct(private Audit $audit, private Ledger $ledger, private OrgDirectory $directory) {}

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

    /**
     * The deposit of a booking arrived (online through the payment link, or at the counter): note
     * it, hold it in the books as the customer's deposit until checkout, and confirm the booking.
     */
    public function depositReceived(Booking $booking, PaymentMeans $means, ?Authenticatable $actor = null): Booking
    {
        $booking = DB::transaction(function () use ($booking, $means, $actor): Booking {
            // The payment webhook and a member of staff may both report it: only the first counts.
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->deposit_paid_at !== null || (float) $booking->deposit <= 0) {
                throw ValidationException::withMessages(['deposit' => __('reservations.errors.no_deposit_due')]);
            }

            $booking->forceFill(['deposit_paid_at' => now()])->save();
            $this->audit->record($booking, 'deposit_received', ['amount' => (string) $booking->deposit, 'means' => $means->value], $actor);
            $this->ledger->post(new LedgerPosting($booking->company_id, $booking->branch_id, $this->directory->today($booking->company_id), 'reservations.deposit', $booking->id, "Deposit {$booking->reference}", [
                ['account' => $means->ledgerAccount(), 'debit' => (string) $booking->deposit],
                ['account' => 'customer_deposits', 'credit' => (string) $booking->deposit],
            ]));

            return $booking;
        });

        return $booking->status === BookingStatus::Pending ? $this->handle($booking, BookingStatus::Confirmed, actor: $actor) : $booking;
    }
}
