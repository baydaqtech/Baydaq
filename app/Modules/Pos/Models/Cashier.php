<?php

namespace App\Modules\Pos\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone allowed to open a shift and sell, identified at the till by a PIN. Usually linked to
 * an HR employee (by id, through the StaffDirectory port).
 */
#[Table('pos__cashiers')]
#[Fillable(['company_id', 'employee_id', 'name', 'is_active'])]
#[Hidden(['pin_hash'])]
class Cashier extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
