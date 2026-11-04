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
     * Register the customer as a client where they are served: the branch, its company or its
     * organization, as the company chooses. Repeat visits change nothing; consent is only added.
     *
     * @param  'staff'|'pos'|'self'|'booking'|'membership'  $source
     */
    public function register(int $customerId, int $branchId, string $source, ?Authenticatable $actor = null, bool $marketingConsent = false): void;

    /**
     * Add to the wallet (top-up, refund, reward). Idempotent per source + reference + kind. With a
     * company, top-ups, rewards and corrections reach that company's books; a top-up names how
     * the money came in.
     *
     * @param  'top_up'|'refund'|'reward'|'adjustment'  $kind
     * @param  'cash'|'card'|'transfer'|null  $paidWith
     */
    public function credit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null, ?string $paidWith = null): string;

    /**
     * Pay from the wallet. Fails with a validation error when the balance is short.
     *
     * @param  'spend'|'adjustment'  $kind
     */
    public function debit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string;

    /**
     * Loyalty for money spent at a company: wallet credit at its loyalty percent, and points at its
     * points per riyal (times the customer's tier). Returns the wallet credit given.
     */
    public function reward(int $customerId, int $companyId, string $spent, string $source, string $reference): string;

    /**
     * Take back the share of a reward whose sale was refunded (half the sale back → half the
     * reward and half the points), at most what is left. Returns the wallet amount taken.
     */
    public function reverseReward(int $customerId, string $source, string $reference, string $share, string $refundSource, string $refundReference): string;

    /**
     * Turn points into wallet credit at the company's point value. Returns the credit given; fails
     * with a validation error when the customer holds fewer points or the company has no value set.
     */
    public function redeemPoints(int $customerId, int $points, int $companyId, ?Authenticatable $actor = null): string;
}
