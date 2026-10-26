<?php

namespace App\Modules\Platform\Data;

final readonly class PaymentLink
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $amount,
        public string $url,
        public string $status,
    ) {}
}
