<?php

namespace App\Modules\Platform\Data;

final readonly class CustomerRef
{
    public function __construct(
        public int $id,
        public string $name,
        public string $phone,
        public ?string $email,
        public string $walletBalance,
    ) {}
}
