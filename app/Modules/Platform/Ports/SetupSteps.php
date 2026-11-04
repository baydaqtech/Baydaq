<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\SetupStep;

/**
 * Steps of getting an account going, for the owner's start page. Each module tags an
 * implementation 'suite.setup' and offers its steps only while it is in the package.
 */
interface SetupSteps
{
    public const TAG = 'suite.setup';

    /** @return list<SetupStep> */
    public function steps(): array;
}
