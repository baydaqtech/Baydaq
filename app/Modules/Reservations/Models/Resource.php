<?php

namespace App\Modules\Reservations\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Reservations\Enums\ResourceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A room, chair, court, table or chalet at a branch. One booking at a time.
 */
#[Table('res__resources')]
#[Fillable(['name_ar', 'name_en', 'kind', 'capacity', 'is_active', 'sort_order'])]
class Resource extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'capacity' => 1, 'kind' => 'room'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ResourceKind::class, 'capacity' => 'integer', 'is_active' => 'boolean'];
    }
}
