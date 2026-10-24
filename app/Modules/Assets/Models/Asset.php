<?php

namespace App\Modules\Assets\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tagged item the company owns, kept at a branch and possibly held by an employee.
 */
#[Table('ast__assets')]
#[Fillable(['category_id', 'name_ar', 'name_en', 'serial', 'model', 'purchase_date', 'cost', 'salvage_value', 'useful_life_months', 'notes'])]
class Asset extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'available', 'cost' => 0, 'salvage_value' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class, 'purchase_date' => 'date', 'disposed_on' => 'date', 'useful_life_months' => 'integer',
            'cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'disposal_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Custody, $this>
     */
    public function custodies(): HasMany
    {
        return $this->hasMany(Custody::class);
    }

    /**
     * @return HasOne<Custody, $this>
     */
    public function openCustody(): HasOne
    {
        return $this->hasOne(Custody::class)->whereNull('returned_on');
    }

    /**
     * @return HasMany<Maintenance, $this>
     */
    public function maintenance(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function lifeMonths(): int
    {
        return $this->useful_life_months ?? $this->category->useful_life_months;
    }

    /**
     * Straight-line book value at the end of a day: cost less (cost − salvage) / life for every
     * full month since purchase, never below salvage. Non-depreciating categories keep their cost.
     */
    public function bookValue(CarbonInterface $on): string
    {
        $cost = BigDecimal::of((string) $this->cost);

        if ($this->purchase_date === null || ! $this->category->depreciates || $this->lifeMonths() === 0) {
            return (string) $cost->toScale(2);
        }

        $months = min($this->lifeMonths(), max(0, (int) $this->purchase_date->diffInMonths($on, false)));
        $monthly = $cost->minus((string) $this->salvage_value)->dividedBy($this->lifeMonths(), 6, RoundingMode::HalfUp);

        return (string) $cost->minus($monthly->multipliedBy($months))->toScale(2, RoundingMode::HalfUp);
    }
}
