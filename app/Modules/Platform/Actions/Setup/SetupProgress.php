<?php

namespace App\Modules\Platform\Actions\Setup;

use App\Modules\Platform\Data\SetupStep;
use App\Modules\Platform\Ports\SetupSteps;

/**
 * Every module's setup steps in order, and how far the account has got.
 */
class SetupProgress
{
    /** @return list<SetupStep> */
    public function steps(): array
    {
        $steps = [];

        foreach (app()->tagged(SetupSteps::TAG) as $source) {
            array_push($steps, ...$source->steps());
        }

        usort($steps, fn (SetupStep $a, SetupStep $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $steps;
    }

    /**
     * @return array{done: int, total: int, next: ?array<string, mixed>}
     */
    public function summary(): array
    {
        $steps = $this->steps();
        $next = collect($steps)->first(fn (SetupStep $step): bool => ! $step->done);

        return ['done' => count(array_filter($steps, fn (SetupStep $step): bool => $step->done)), 'total' => count($steps), 'next' => $next ? (array) $next : null];
    }
}
