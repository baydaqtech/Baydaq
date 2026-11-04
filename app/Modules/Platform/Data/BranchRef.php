<?php

namespace App\Modules\Platform\Data;

final readonly class BranchRef
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $code,
        public string $name,
        public string $nameAr,
        public string $nameEn,
        public bool $isHeadOffice,
        public bool $isActive,
    ) {}
}
