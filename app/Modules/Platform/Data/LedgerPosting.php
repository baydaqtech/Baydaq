<?php

namespace App\Modules\Platform\Data;

/**
 * One business event to record in the books, from the module where it happened. Lines name
 * accounts by their system key (e.g. 'cash', 'sales', 'vat_output'), so modules never need to know
 * the account codes; the finance area itself may name an account by id as '#12'. `taxBase` on a VAT line is the net amount the VAT was charged on.
 */
final readonly class LedgerPosting
{
    /**
     * @param  list<array{account: string, debit?: string, credit?: string, tax_base?: ?string, memo?: ?string}>  $lines
     */
    public function __construct(
        public int $companyId,
        public ?int $branchId,
        public string $date,
        public string $source,
        public int $sourceId,
        public string $memo,
        public array $lines,
    ) {}
}
