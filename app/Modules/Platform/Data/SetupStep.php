<?php

namespace App\Modules\Platform\Data;

/**
 * One step of setting up the account: what to do ($title, $hint, translated), whether it is done,
 * and where to do it ($url, a suite path). Lower $order comes first.
 */
final readonly class SetupStep
{
    public function __construct(
        public string $key,
        public string $title,
        public string $hint,
        public bool $done,
        public string $url,
        public int $order = 50,
    ) {}
}
