<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Setup\SetupProgress;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Subscription;
use Inertia\Response;

class HomeController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function __invoke(SetupProgress $progress): Response
    {
        $subscriptions = Subscription::query()->whereIn('status', ['active', 'lapsed'])->get();
        // Those who set the account up see how far it has got, until every step is done.
        $setup = $this->access()->branches('suite.structure.update')->all ? $progress->summary() : null;

        return $this->suitePage('Suite/Home', [
            'setup' => $setup !== null && $setup['done'] < $setup['total'] ? $setup : null,
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
