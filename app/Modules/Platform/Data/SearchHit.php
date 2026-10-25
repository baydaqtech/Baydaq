<?php

namespace App\Modules\Platform\Data;

/**
 * One ⌘K search result: what it is, how to show it, and its suite path inside the current scope
 * (e.g. /hr/employees/12).
 */
final readonly class SearchHit
{
    public function __construct(
        public string $type,
        public string $label,
        public ?string $detail,
        public string $path,
    ) {}
}
