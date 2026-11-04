<?php

namespace App\Modules\Platform\Data;

/**
 * One request waiting for a decision. $kind groups the inbox (leave, advance, expense…); $title
 * is who asked, $detail what for (both translated). Dates are Y-m-d for the page to format; $url
 * and the decisions' URLs are suite paths relative to the scope.
 */
final readonly class ApprovalItem
{
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public string $detail,
        public string $since,
        public ?string $date = null,
        public ?string $endDate = null,
        public ?string $amount = null,
        public ?string $url = null,
        public ?ApprovalDecision $approve = null,
        public ?ApprovalDecision $reject = null,
    ) {}
}
