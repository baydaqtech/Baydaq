<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\CustomerRef;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\WalletEntry;
use App\Modules\Platform\Ports\Customers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletCustomers implements Customers
{
    public function identify(string $name, string $phone, ?string $email = null): ?CustomerRef
    {
        $normalized = Customer::normalizePhone($phone);

        if (strlen($normalized) < 8) {
            return null;
        }

        $customer = Customer::query()->where('phone', $normalized)->first()
            ?? (new Customer(['name' => $name, 'email' => $email]))->forceFill(['phone' => $normalized]);

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

    public function credit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string
    {
        return $this->move($customerId, BigDecimal::of($amount)->abs(), $kind, $source, $reference, $companyId, $note, $actor);
    }

    public function debit(int $customerId, string $amount, string $kind, string $source, ?string $reference = null, ?int $companyId = null, ?string $note = null, ?Authenticatable $actor = null): string
    {
        return $this->move($customerId, BigDecimal::of($amount)->abs()->negated(), $kind, $source, $reference, $companyId, $note, $actor);
    }

    public function reward(int $customerId, int $companyId, string $spent, string $source, string $reference): string
    {
        $percent = Company::query()->whereKey($companyId)->value('loyalty_percent');

        if ($percent === null || (float) $percent <= 0) {
            return '0.00';
        }

        $credit = BigDecimal::of($spent)->multipliedBy((string) $percent)->dividedBy(100, 2, RoundingMode::Down);

        if (! $credit->isPositive()) {
            return '0.00';
        }

        $this->credit($customerId, (string) $credit, 'reward', $source, $reference, $companyId);

        return (string) $credit;
    }

    private function move(int $customerId, BigDecimal $amount, string $kind, string $source, ?string $reference, ?int $companyId, ?string $note, ?Authenticatable $actor): string
    {
        return DB::transaction(function () use ($customerId, $amount, $kind, $source, $reference, $companyId, $note, $actor): string {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            if ($reference !== null && WalletEntry::query()->where(['source' => $source, 'reference' => $reference, 'kind' => $kind])->exists()) {
                return (string) $customer->wallet_balance;
            }

            $balance = BigDecimal::of((string) $customer->wallet_balance)->plus($amount)->toScale(2);

            if ($balance->isNegative()) {
                throw ValidationException::withMessages(['wallet' => __('suite.customers.insufficient', ['balance' => (string) $customer->wallet_balance])]);
            }

            (new WalletEntry)->forceFill([
                'customer_id' => $customer->id, 'company_id' => $companyId, 'amount' => (string) $amount->toScale(2), 'balance_after' => (string) $balance,
                'kind' => $kind, 'source' => $source, 'reference' => $reference, 'note' => $note, 'user_id' => $actor?->getAuthIdentifier(),
            ])->save();
            $customer->forceFill(['wallet_balance' => (string) $balance])->save();

            return (string) $balance;
        });
    }

    private function ref(Customer $customer): CustomerRef
    {
        return new CustomerRef($customer->id, $customer->name, $customer->phone, $customer->email, (string) $customer->wallet_balance);
    }
}
