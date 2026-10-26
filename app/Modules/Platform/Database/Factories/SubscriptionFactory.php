<?php

namespace App\Modules\Platform\Database\Factories;

use App\Models\Client;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'module' => Module::Hr,
            'billing_unit' => Module::Hr->billingUnit(),
            'quantity' => 50,
            'unit_price' => '150.00',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
        ];
    }

    public function module(Module $module): static
    {
        return $this->state(fn (): array => ['module' => $module, 'billing_unit' => $module->billingUnit()]);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn (): array => ['quantity' => $seats]);
    }

    public function lapsed(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Lapsed,
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }
}
