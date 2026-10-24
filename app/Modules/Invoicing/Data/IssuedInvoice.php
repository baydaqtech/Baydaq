<?php

namespace App\Modules\Invoicing\Data;

final readonly class IssuedInvoice
{
    public function __construct(public int $id, public ?string $number, public string $status, public ?string $qr, public string $total, public string $vat) {}
}
