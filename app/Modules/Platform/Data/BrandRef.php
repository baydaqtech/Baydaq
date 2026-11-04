<?php

namespace App\Modules\Platform\Data;

final readonly class BrandRef
{
    /**
     * @param  list<int>  $branchIds
     */
    public function __construct(public int $id, public int $companyId, public string $slug, public string $name, public string $nameAr, public string $nameEn, public ?string $color, public array $branchIds) {}
}
