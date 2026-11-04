<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Setup\SetupProgress;
use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Inertia\Response;

/**
 * Getting started: every module's setup steps in order, the next one open, each leading to the
 * page where it is done. The page reads the account as it is, so a step done anywhere shows here.
 */
class StartController extends Controller
{
    use RendersSuitePages;

    public function __invoke(SetupProgress $progress): Response
    {
        return $this->suitePage('Suite/Start', [
            'steps' => array_map(fn (SetupStep $step): array => (array) $step, $progress->steps()),
        ]);
    }
}
