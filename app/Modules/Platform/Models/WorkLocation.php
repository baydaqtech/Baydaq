<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Database\Factories\WorkLocationFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A geofence where people can check in, linked to the branches it serves.
 *
 * @property list<string>|null $wifi_bssids
 */
#[Table('platform__work_locations')]
#[Fillable(['name_ar', 'name_en', 'latitude', 'longitude', 'radius_m', 'wifi_bssids', 'is_active'])]
class WorkLocation extends Model
{
    /** @use HasFactory<WorkLocationFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'radius_m' => 100,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'wifi_bssids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'platform__work_location_branch');
    }

    protected static function newFactory(): WorkLocationFactory
    {
        return WorkLocationFactory::new();
    }
}
