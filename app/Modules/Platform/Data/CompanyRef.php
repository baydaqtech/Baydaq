<?php

namespace App\Modules\Platform\Data;

/**
 * A company as other modules see it: enough to label it and place records under it.
 */
final readonly class CompanyRef
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $slug,
        public string $name,
        public string $nameAr,
        public string $nameEn,
        public string $timezone,
        public bool $isActive,
        public ?string $vatNumber = null,
        public ?string $crNumber = null,
        /** @var array{street: ?string, building_number: ?string, district: ?string, city: ?string, postal_code: ?string} */
        public array $address = ['street' => null, 'building_number' => null, 'district' => null, 'city' => null, 'postal_code' => null],
    ) {}
}
