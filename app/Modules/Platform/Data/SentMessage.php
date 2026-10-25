<?php

namespace App\Modules\Platform\Data;

final readonly class SentMessage
{
    public function __construct(
        public int $id,
        public string $status,
        public string $body,
    ) {}
}
