<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\CustomerRef;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The account's shared customer record and wallet (X2). Modules identify a customer by phone
 * when they sell, book or subscribe, keep the id, and move money through the wallet here.
 */
interface Customers
{
    /**
     * The customer with this phone, created on first sight; an empty name is filled in. Null when
     * the phone is too short to be a real number.
     */
    public function identify(string $name, string $phone, ?string $email = null): ?CustomerRef;

    public function find(int $customerId): ?CustomerRef;

    /**
     * Add to the wallet (top-up, refund, reward). Idempotent per source + reference + kind.
     *
     * @param  'top_up'|'refund'|'reward'|'adjustment'  $kind
     */
    public function credit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string;

    /**
     * Pay from the wallet. Fails with a validation error when the balance is short.
     *
     * @param  'spend'|'adjustment'  $kind
     */
    public function debit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string;

    /** Loyalty credit for money spent at a company, at that company's loyalty percent. Returns the credit given. */
    public function reward(int $customerId, int $companyId, string $spent, string $source, string $reference): string;
}
