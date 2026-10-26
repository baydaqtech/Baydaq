<?php

namespace App\Modules\Reservations\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * How a booking was paid at checkout, with the UN/ECE 4461 code ZATCA expects.
 */
enum PaymentMeans: string
{
    use HasLabels;

    const LABELS = 'payment_means';

    case Cash = '10';
    case Card = '48';
    case Transfer = '42';

    protected static function labelFile(): string
    {
        return 'reservations';
    }
}
