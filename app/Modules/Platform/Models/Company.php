<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Database\Factories\CompanyFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A legal entity (commercial registration) inside an organization. It employs people,
 * owns branches and carries the government identifiers.
 */
#[Table('platform__companies')]
#[Fillable([
    'organization_id', 'slug', 'name_ar', 'name_en', 'cr_number', 'vat_number', 'unified_number',
    'gosi_number', 'qiwa_id', 'street', 'building_number', 'district', 'city', 'postal_code', 'timezone', 'status',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Asia/Riyadh',
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasOne<Branch, $this>
     */
    public function headOffice(): HasOne
    {
        return $this->hasOne(Branch::class)->where('is_head_office', true);
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
