<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\CustomerRef;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\CustomerRegistration;
use App\Modules\Platform\Models\PointEntry;
use App\Modules\Platform\Models\WalletEntry;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletCustomers implements Customers
{
    /** Where money paid into a wallet lands. */
    private const PAID_WITH_ACCOUNTS = ['cash' => 'cash', 'card' => 'card_clearing', 'transfer' => 'bank'];

    public function __construct(private Ledger $ledger, private OrgDirectory $directory) {}

    public function identify(string $name, string $phone, ?string $email = null): ?CustomerRef
    {
        $normalized = Customer::normalizePhone($phone);

        if (strlen($normalized) < 8) {
            return null;
        }

        $customer = Customer::query()->where('phone', $normalized)->first()
            ?? (new Customer(['name' => $name, 'email' => $email]))->forceFill(['phone' => $normalized, 'client_id' => $this->clientId()]);

        if (! $customer->exists || ($customer->name === '' && $name !== '') || ($customer->email === null && $email)) {
            $customer->fill(['name' => $customer->name ?: $name, 'email' => $customer->email ?? $email])->save();
        }

        return $this->ref($customer);
    }

    public function find(int $customerId): ?CustomerRef
    {
        $customer = Customer::query()->find($customerId);

        return $customer ? $this->ref($customer) : null;
    }

    public function register(int $customerId, int $branchId, string $source, ?Authenticatable $actor = null, bool $marketingConsent = false): void
    {
        $branch = Branch::query()->with('company')->find($branchId);

        if ($branch === null) {
            return;
        }

        [$scopeType, $scopeId] = match ($branch->company->client_scope) {
            'organization' => ['organization', $branch->company->organization_id],
            'branch' => ['branch', $branch->id],
            default => ['company', $branch->company_id],
        };

        $registration = CustomerRegistration::query()->where(['customer_id' => $customerId, 'scope_type' => $scopeType, 'scope_id' => $scopeId])->first();

        if ($registration === null) {
            (new CustomerRegistration)->forceFill([
                'client_id' => $this->clientId(), 'customer_id' => $customerId, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'branch_id' => $branch->id,
                'source' => $source, 'registered_by' => $actor?->getAuthIdentifier(), 'marketing_consent_at' => $marketingConsent ? now() : null,
            ])->save();
        } elseif ($marketingConsent && $registration->marketing_consent_at === null) {
            $registration->forceFill(['marketing_consent_at' => now()])->save();
        }
    }

    public function credit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null, ?string $paidWith = null): string
    {
        return $this->move($customerId, BigDecimal::of($amount)->abs(), $kind, $source, $reference, $companyId, $note, $actor, $paidWith);
    }

    public function debit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string
    {
        return $this->move($customerId, BigDecimal::of($amount)->abs()->negated(), $kind, $source, $reference, $companyId, $note, $actor);
    }

    public function reward(int $customerId, int $companyId, string $spent, string $source, string $reference): string
    {
        $company = Company::query()->find($companyId);

        if ($company === null) {
            return '0.00';
        }

        if ($company->earnsPoints()) {
            $this->earnPoints($customerId, $company, BigDecimal::of($spent), $source, $reference);
        }

        if ($company->loyalty_percent === null || (float) $company->loyalty_percent <= 0) {
            return '0.00';
        }

        $credit = BigDecimal::of($spent)->multipliedBy((string) $company->loyalty_percent)->dividedBy(100, 2, RoundingMode::Down);

        if (! $credit->isPositive()) {
            return '0.00';
        }

        $this->credit($customerId, (string) $credit, 'reward', $source, $reference, $companyId);

        return (string) $credit;
    }

    public function reverseReward(int $customerId, string $source, string $reference, string $share, string $refundSource, string $refundReference): string
    {
        $earned = PointEntry::query()->where(['customer_id' => $customerId, 'source' => $source, 'reference' => $reference, 'kind' => 'earn'])->first();

        if ($earned !== null) {
            $points = (int) BigDecimal::of((string) $earned->points)->multipliedBy($share)->toScale(0, RoundingMode::HalfUp)->toInt();
            $this->movePoints($customerId, -$points, 'reverse', $refundSource, $refundReference, $earned->company_id, null, capped: true);
        }

        $reward = WalletEntry::query()->where(['customer_id' => $customerId, 'source' => $source, 'reference' => $reference, 'kind' => 'reward'])->first();

        if ($reward === null) {
            return '0.00';
        }

        $balance = BigDecimal::of((string) Customer::query()->whereKey($customerId)->value('wallet_balance'));
        $back = BigDecimal::min(BigDecimal::of((string) $reward->amount)->multipliedBy($share)->toScale(2, RoundingMode::HalfUp), $balance);

        if (! $back->isPositive()) {
            return '0.00';
        }

        $this->move($customerId, $back->negated(), 'reward', $refundSource, $refundReference, $reward->company_id, __('suite.customers.reward_reversed'), null);

        return (string) $back;
    }

    public function redeemPoints(int $customerId, int $points, int $companyId, ?Authenticatable $actor = null): string
    {
        $company = Company::query()->findOrFail($companyId);
        $value = BigDecimal::of((string) ($company->point_value ?? 0));

        if ($points <= 0 || ! $value->isPositive()) {
            throw ValidationException::withMessages(['points' => __('suite.customers.points_no_value')]);
        }

        $credit = $value->multipliedBy($points)->toScale(2, RoundingMode::Down);

        return DB::transaction(function () use ($customerId, $points, $company, $credit, $actor): string {
            $entry = $this->movePoints($customerId, -$points, 'redeem', 'platform.points', null, $company->id, $actor);
            $this->move($customerId, $credit, 'reward', 'platform.points', (string) $entry->id, $company->id, __('suite.customers.points_redeemed', ['points' => $points]), $actor);

            return (string) $credit;
        });
    }

    /**
     * Points for a sale: per riyal at the company's rate, times the tier the customer stands in.
     */
    private function earnPoints(int $customerId, Company $company, BigDecimal $spent, string $source, string $reference): void
    {
        $lifetime = (int) Customer::query()->whereKey($customerId)->value('lifetime_points');
        $multiplier = (string) ($company->tierFor($lifetime)['multiplier'] ?? 1);
        $points = $spent->multipliedBy((string) $company->points_per_riyal)->multipliedBy($multiplier)->toScale(0, RoundingMode::Down)->toInt();

        if ($points > 0) {
            $this->movePoints($customerId, $points, 'earn', $source, $reference, $company->id, null);
        }
    }

    /**
     * Change a customer's points under a lock on the customer: once per source + reference + kind,
     * never below zero (a take-back is capped at what is left; a redemption beyond it is refused).
     */
    private function movePoints(int $customerId, int $points, string $kind, string $source, ?string $reference, ?int $companyId, ?Authenticatable $actor, bool $capped = false): PointEntry
    {
        return DB::transaction(function () use ($customerId, $points, $kind, $source, $reference, $companyId, $actor, $capped): PointEntry {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            $done = $reference !== null ? PointEntry::query()->where(['source' => $source, 'reference' => $reference, 'kind' => $kind])->first() : null;

            if ($done !== null) {
                return $done;
            }

            if ($customer->points + $points < 0 && ! $capped) {
                throw ValidationException::withMessages(['points' => __('suite.customers.points_short', ['points' => $customer->points])]);
            }

            $points = max($points, -$customer->points);

            $entry = (new PointEntry)->forceFill([
                'client_id' => $customer->client_id, 'customer_id' => $customer->id, 'company_id' => $companyId, 'points' => $points,
                'balance_after' => $customer->points + $points, 'kind' => $kind, 'source' => $source, 'reference' => $reference, 'user_id' => $actor?->getAuthIdentifier(),
            ]);
            $entry->save();
            $customer->forceFill([
                'points' => $customer->points + $points,
                'lifetime_points' => $customer->lifetime_points + ($kind === 'earn' ? $points : 0),
            ])->save();

            return $entry;
        });
    }

    private function move(int $customerId, BigDecimal $amount, string $kind, string $source, ?string $reference, ?int $companyId, ?string $note, ?Authenticatable $actor, ?string $paidWith = null): string
    {
        return DB::transaction(function () use ($customerId, $amount, $kind, $source, $reference, $companyId, $note, $actor, $paidWith): string {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            if ($reference !== null && WalletEntry::query()->where(['source' => $source, 'reference' => $reference, 'kind' => $kind])->exists()) {
                return (string) $customer->wallet_balance;
            }

            $balance = BigDecimal::of((string) $customer->wallet_balance)->plus($amount)->toScale(2);

            if ($balance->isNegative()) {
                throw ValidationException::withMessages(['wallet' => __('suite.customers.insufficient', ['balance' => (string) $customer->wallet_balance])]);
            }

            $entry = (new WalletEntry)->forceFill([
                'client_id' => $customer->client_id, 'customer_id' => $customer->id, 'company_id' => $companyId, 'amount' => (string) $amount->toScale(2), 'balance_after' => (string) $balance,
                'kind' => $kind, 'source' => $source, 'reference' => $reference, 'note' => $note, 'user_id' => $actor?->getAuthIdentifier(),
            ]);
            $entry->save();
            $customer->forceFill(['wallet_balance' => (string) $balance])->save();

            if ($companyId !== null && ($posting = self::posting($entry, $this->directory->today($companyId), $paidWith)) !== null) {
                $this->ledger->post($posting);
            }

            return (string) $balance;
        });
    }

    /**
     * A wallet movement for the books of its company. The balances are a debt to the customers:
     * a top-up is money in against that debt, a reward is a loyalty cost, a correction is other
     * income or expense. Spending and refunds are posted by the sale or refund itself.
     */
    public static function posting(WalletEntry $entry, string $date, ?string $paidWith = null): ?LedgerPosting
    {
        $amount = BigDecimal::of((string) $entry->amount);

        $other = match (true) {
            $entry->company_id === null => null,
            $entry->kind === 'top_up' => self::PAID_WITH_ACCOUNTS[$paidWith ?? 'cash'] ?? 'cash',
            $entry->kind === 'reward' => 'loyalty',
            $entry->kind === 'adjustment' => $amount->isPositive() ? 'other_expense' : 'other_income',
            default => null,
        };

        if ($other === null || $amount->isZero()) {
            return null;
        }

        $value = (string) $amount->abs()->toScale(2);

        return new LedgerPosting($entry->company_id, null, $date, 'platform.wallet', $entry->id, "Wallet {$entry->kind}", $amount->isPositive()
            ? [['account' => $other, 'debit' => $value], ['account' => 'customer_wallets', 'credit' => $value]]
            : [['account' => 'customer_wallets', 'debit' => $value], ['account' => $other, 'credit' => $value]]);
    }

    private function ref(Customer $customer): CustomerRef
    {
        return new CustomerRef($customer->id, $customer->name, $customer->phone, $customer->email, (string) $customer->wallet_balance);
    }

    /** Set explicitly rather than only through the tenant hook: seeders run without model events. */
    private function clientId(): int
    {
        return app(CurrentTenant::class)->client()->id;
    }
}
