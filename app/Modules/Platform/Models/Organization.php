<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Database\Factories\OrganizationFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The top of a client's tree: a holding or group that owns companies.
 */
#[Table('platform__organizations')]
#[Fillable(['slug', 'name_ar', 'name_en', 'status'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
