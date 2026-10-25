<?php

namespace App\Modules\Platform\Actions\Customers;

use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\CustomerData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * PDPL erasure: the customer's name, phone, email, birth date and consents go, here and in every
 * module's copies. Sales, bookings and wallet movements stay (tax and accounting need them), now
 * tied to an anonymous record.
 */
class EraseCustomer
{
    public function __construct(private Audit $audit) {}

    public function handle(Customer $customer, ?Authenticatable $actor = null): Customer
    {
        return DB::transaction(function () use ($customer, $actor): Customer {
            foreach (app()->tagged(CustomerData::TAG) as $module) {
                $module->erase($customer->id);
            }

            $customer->registrations()->update(['marketing_consent_at' => null]);
            $customer->forceFill([
                'name' => '—', 'phone' => 'erased-'.$customer->id, 'email' => null, 'birth_date' => null, 'gender' => null, 'notes' => null, 'erased_at' => now(),
            ])->save();
            $this->audit->record($customer, 'erased', [], $actor);

            return $customer;
        });
    }

    /**
     * Everything Baydaq holds about the customer, for an access request.
     *
     * @return array<string, mixed>
     */
    public static function export(Customer $customer): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'customer' => $customer->only(['name', 'phone', 'email', 'birth_date', 'gender', 'notes', 'wallet_balance', 'created_at']),
            'registrations' => $customer->registrations()->get(['scope_type', 'source', 'marketing_consent_at', 'created_at'])->toArray(),
            'wallet' => $customer->walletEntries()->get(['amount', 'kind', 'source', 'note', 'created_at'])->toArray(),
            ...collect(app()->tagged(CustomerData::TAG))->reduce(fn (array $all, CustomerData $module): array => [...$all, ...$module->export($customer->id)], []),
        ];
    }
}
