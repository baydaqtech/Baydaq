<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Assets\Enums\WarehouseKind;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Where stock is kept: the main store, a branch store, a vehicle, an event site. Its keeper (an HR
 * employee) can hand stock out and take it back from the app.
 */
#[Table('ast__warehouses')]
#[Fillable(['name_ar', 'name_en', 'kind', 'keeper_employee_id', 'is_active'])]
class Warehouse extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['kind' => 'store', 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => WarehouseKind::class, 'keeper_employee_id' => 'integer', 'is_active' => 'boolean'];
    }
}
