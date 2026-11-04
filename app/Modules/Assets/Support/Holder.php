<?php

namespace App\Modules\Assets\Support;

use App\Modules\Assets\Enums\HolderType;

/**
 * Who has stock: a warehouse, an employee (HR id), or a transfer/handover while it is on its way.
 */
final readonly class Holder
{
    public function __construct(public HolderType $type, public int $id) {}

    public static function warehouse(int $id): self
    {
        return new self(HolderType::Warehouse, $id);
    }

    public static function employee(int $id): self
    {
        return new self(HolderType::Employee, $id);
    }

    public static function of(string $type, int $id): self
    {
        return new self(HolderType::from($type), $id);
    }

    public function is(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
