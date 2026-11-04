<?php

namespace App\Modules\Invoicing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invoice was accepted by ZATCA. Carries ids and figures only.
 */
final class InvoiceIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $invoiceId, public readonly int $companyId, public readonly string $number, public readonly string $total) {}
}
