<?php

namespace App\Modules\Pos\Adapters;

use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\SetupSteps;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;

/** The point of sale's step: a menu to sell from and a register to sell at. */
class PosSetupSteps implements SetupSteps
{
    public function __construct(private Entitlements $entitlements) {}

    public function steps(): array
    {
        if (! $this->entitlements->isActive(Module::Pos)) {
            return [];
        }

        return [new SetupStep('pos', __('suite.start.steps.pos.title'), __('suite.start.steps.pos.hint'),
            Product::query()->exists() && Register::query()->exists(), '/pos/catalog', 80)];
    }
}
