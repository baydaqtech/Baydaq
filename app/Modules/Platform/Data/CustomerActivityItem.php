<?php

namespace App\Modules\Platform\Data;

/**
 * Something a customer did in a module (an order, a booking, a membership), for the customer's
 * profile. $path is a suite path inside the current scope.
 */
final readonly class CustomerActivityItem
{
    public function __construct(
        public string $module,
        public string $label,
        public ?string $detail,
        public string $date,
        public ?string $amount,
        public ?string $path,
    ) {}
}
