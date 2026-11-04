<?php

namespace App\Models;

use App\Enums\PricingModel;
use App\Models\Concerns\HasBilingualFields;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An entry in the platform services catalog (email, OTP, SMS, ...).
 */
#[Fillable([
    'key', 'name_ar', 'name_en', 'description_ar', 'description_en', 'icon',
    'is_active', 'sort_order', 'docs_url', 'default_price', 'pricing_model', 'unit',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'pricing_model' => 'monthly',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'default_price' => 'decimal:2',
            'pricing_model' => PricingModel::class,
        ];
    }

    /**
     * Active catalog entries in display order.
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectService, $this>
     */
    public function projectServices(): HasMany
    {
        return $this->hasMany(ProjectService::class);
    }
}
