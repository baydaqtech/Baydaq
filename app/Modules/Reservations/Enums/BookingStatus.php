<?php

namespace App\Modules\Reservations\Enums;

use App\Enums\Concerns\HasLabels;

enum BookingStatus: string
{
    use HasLabels;

    const LABELS = 'booking_status';

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Statuses that hold the resource and staff member for the slot. */
    public function blocksSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::CheckedIn, self::Completed], true);
    }

    /**
     * Where a booking may go from here (checkout is the only way to Completed).
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::Cancelled],
            default => [],
        };
    }

    /** @return list<string> */
    public static function blocking(): array
    {
        return array_map(fn (self $status): string => $status->value, array_filter(self::cases(), fn (self $status): bool => $status->blocksSlot()));
    }

    protected static function labelFile(): string
    {
        return 'reservations';
    }
}
