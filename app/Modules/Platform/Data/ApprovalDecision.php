<?php

namespace App\Modules\Platform\Data;

/**
 * How the inbox sends a decision: the module's own route (method, suite path, payload), and the
 * field that carries a short note when the decision asks for one.
 */
final readonly class ApprovalDecision
{
    /**
     * @param  'post'|'put'  $method
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $data = [],
        public ?string $noteField = null,
    ) {}
}
