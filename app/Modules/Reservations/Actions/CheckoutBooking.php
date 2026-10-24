<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Enums\PaymentMeans;
use App\Modules\Reservations\Events\BookingCompleted;
use App\Modules\Reservations\Models\Booking;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutBooking
{
    public function __construct(private InvoiceIssuer $invoices, private Entitlements $entitlements, private Audit $audit) {}

    /**
     * Take the balance (price less a received deposit) and complete the booking. When the tenant
     * has Invoicing and the company a ready e-invoicing unit, the full price is invoiced as a
     * simplified (B2C) tax invoice through the Invoicing port.
     */
    public function handle(Booking $booking, PaymentMeans $means, ?Authenticatable $actor = null): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
            throw ValidationException::withMessages(['status' => __('reservations.errors.not_checkoutable')]);
        }

        return DB::transaction(function () use ($booking, $means, $actor): Booking {
            $booking->loadMissing('service');
            $due = BigDecimal::of((string) $booking->price)->minus($booking->deposit_paid_at ? (string) $booking->deposit : '0');

            $booking->forceFill(['status' => BookingStatus::Completed->value, 'paid_amount' => (string) $booking->price])->save();

            $unitId = $this->entitlements->isActive(Module::Invoicing) ? $this->invoices->readyUnitFor($booking->company_id) : null;

            if ($unitId !== null) {
                $issued = $this->invoices->issueSimplified($booking->company_id, $unitId, [[
                    'description' => $booking->service->localized('name').' · '.$booking->starts_at->format('Y-m-d H:i'),
                    'quantity' => 1,
                    'unit_price_incl_vat' => (string) $booking->price,
                    'vat_rate' => (string) $booking->service->vat_rate,
                ]], $means->value, $booking->reference);

                $booking->forceFill(['invoice_id' => $issued->id])->save();
            }

            $this->audit->record($booking, 'checked_out', ['collected' => (string) $due->toScale(2), 'means' => $means->value], $actor);
            BookingCompleted::dispatch($booking->id, $booking->company_id, $booking->branch_id, (string) $booking->price, $booking->employee_id);

            return $booking;
        });
    }
}
