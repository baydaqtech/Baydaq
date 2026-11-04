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
    'gosi_number', 'qiwa_id', 'street', 'building_number', 'district', 'city', 'postal_code', 'timezone', 'loyalty_percent', 'points_per_riyal', 'point_value', 'points_tiers', 'client_scope', 'status',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    public const DEFAULT_TIMEZONE = 'Asia/Riyadh';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => self::DEFAULT_TIMEZONE,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['points_per_riyal' => 'decimal:2', 'point_value' => 'decimal:4', 'points_tiers' => 'array'];
    }

    public function earnsPoints(): bool
    {
        return $this->points_per_riyal !== null && (float) $this->points_per_riyal > 0;
    }

    /**
     * The tier a customer stands in at this company, by the points they ever earned (the highest
     * tier they reached), or null below the first.
     *
     * @return array{name_ar: string, name_en: string, from: int, multiplier: float}|null
     */
    public function tierFor(int $lifetimePoints): ?array
    {
        return collect($this->points_tiers ?? [])
            ->filter(fn (array $tier): bool => $lifetimePoints >= (int) $tier['from'])
            ->sortByDesc(fn (array $tier): int => (int) $tier['from'])
            ->first();
    }

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
