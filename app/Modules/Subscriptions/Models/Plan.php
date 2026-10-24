<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Subscriptions\Enums\PeriodUnit;
use App\Modules\Subscriptions\Enums\PlanGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What a member buys: a period (1 month, 3 months, a year), a price including VAT, optionally a
 * number of visits, freeze days and a gender zone. Valid at the chosen branches, or all of the
 * company's branches when none are chosen.
 */
#[Table('sub__plans')]
#[Fillable(['company_id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'period_unit', 'period_count', 'price', 'vat_rate', 'sessions', 'freeze_days', 'gender', 'is_active', 'sort_order'])]
class Plan extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['period_unit' => 'month', 'period_count' => 1, 'vat_rate' => 15, 'freeze_days' => 0, 'gender' => 'any', 'is_active' => true, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_unit' => PeriodUnit::class, 'gender' => PlanGender::class, 'period_count' => 'integer', 'sessions' => 'integer', 'freeze_days' => 'integer',
            'price' => 'decimal:2', 'vat_rate' => 'decimal:2', 'is_active' => 'boolean',
        ];
    }

    /** @return list<int> branch ids (empty = every branch of the company) */
    public function branchIds(): array
    {
        return $this->getConnection()->table('sub__plan_branch')->where('plan_id', $this->id)->orderBy('branch_id')->pluck('branch_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param list<int> $branchIds */
    public function syncBranches(array $branchIds): void
    {
        $this->getConnection()->table('sub__plan_branch')->where('plan_id', $this->id)->delete();
        $this->getConnection()->table('sub__plan_branch')->insert(array_map(fn (int $id): array => ['plan_id' => $this->id, 'branch_id' => $id], $branchIds));
    }
}
