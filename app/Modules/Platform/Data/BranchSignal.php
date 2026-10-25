<?php

namespace App\Modules\Platform\Data;

/**
 * One health reading of a branch on the owner board, e.g. attendance today 92% (good).
 * $label is already translated; $status is good, warn, bad or info.
 */
final readonly class BranchSignal
{
    /**
     * @param  'good'|'warn'|'bad'|'info'  $status
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
        public string $status,
    ) {}
}
