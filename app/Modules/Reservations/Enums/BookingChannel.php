<?php

namespace App\Modules\Reservations\Enums;

use App\Enums\Concerns\HasLabels;

enum BookingChannel: string
{
    use HasLabels;

    const LABELS = 'booking_channel';

    case Staff = 'staff';
    case Online = 'online';

    protected static function labelFile(): string
    {
        return 'reservations';
    }
}
