<?php

namespace App\Modules\Reservations\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A booking was served and paid. Carries ids and amounts only (HR may later count commissions
 * per employee from it).
 */
final class BookingCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly string $total,
        public readonly ?int $employeeId,
    ) {}
}
