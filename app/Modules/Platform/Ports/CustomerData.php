<?php

namespace App\Modules\Platform\Ports;

/**
 * The personal data a module keeps about a customer, for the PDPL toolkit: a copy for the
 * customer's access request, and erasure (names and phones removed, the business records kept
 * for tax and accounting). Modules tag an implementation 'customer.data'.
 */
interface CustomerData
{
    public const TAG = 'customer.data';

    /** @return array<string, list<array<string, mixed>>> rows by kind, e.g. ['orders' => [...]] */
    public function export(int $customerId): array;

    /** Remove the personal details from this module's copies. Returns how many rows changed. */
    public function erase(int $customerId): int;
}
