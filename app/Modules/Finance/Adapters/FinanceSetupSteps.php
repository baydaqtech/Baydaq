<?php

namespace App\Modules\Finance\Adapters;

use App\Modules\Finance\Actions\OpeningBalances;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\SetupSteps;

/** Finance's step: where the books start (opening balances). */
class FinanceSetupSteps implements SetupSteps
{
    public function __construct(private Entitlements $entitlements) {}

    public function steps(): array
    {
        if (! $this->entitlements->hasFeature('finance.accounting')) {
            return [];
        }

        return [new SetupStep('opening', __('suite.start.steps.opening.title'), __('suite.start.steps.opening.hint'),
            JournalEntry::query()->where('source', OpeningBalances::SOURCE)->exists(), '/finance/periods', 70)];
    }
}
