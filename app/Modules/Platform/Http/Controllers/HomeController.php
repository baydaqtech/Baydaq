<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Subscription;
use Inertia\Response;

class HomeController extends Controller
{
    use RendersSuitePages;

    public function __invoke(): Response
    {
        $subscriptions = Subscription::query()->whereIn('status', ['active', 'lapsed'])->get();

        return $this->suitePage('Suite/Home', [
            'modules' => $subscriptions->map(fn (Subscription $subscription): array => [
                'module' => $subscription->module->value,
                'label' => $subscription->module->label(),
                'active' => $subscription->isActive(),
                'quantity' => $subscription->quantity,
                'unit' => $subscription->billing_unit->value,
                'ends_on' => $subscription->ends_on?->toDateString(),
            ])->values()->all(),
            'available' => array_map(fn (Module $module): array => ['module' => $module->value, 'label' => $module->label()], Module::cases()),
        ]);
    }
}
