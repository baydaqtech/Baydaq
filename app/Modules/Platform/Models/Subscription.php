<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\SubscriptionFactory;
use App\Modules\Platform\Enums\BillingUnit;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One module bought by a client, with the quantity cap (seats, registers, branches...).
 * Managed by Baydaq staff from the admin.
 */
#[Table('platform__subscriptions')]
#[Fillable(['module', 'status', 'billing_unit', 'quantity', 'unit_price', 'starts_on', 'ends_on', 'notes'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToClient, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => Module::class,
            'status' => SubscriptionStatus::class,
            'billing_unit' => BillingUnit::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * The subscription that lets a module be used today (active or lapsed, i.e. read-only).
     *
     * @param  Builder<Subscription>  $query
     */
    #[Scope]
    protected function forModule(Builder $query, Module $module): void
    {
        $query->where('module', $module)->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Lapsed]);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && ($this->ends_on === null || ! $this->ends_on->isPast());
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
