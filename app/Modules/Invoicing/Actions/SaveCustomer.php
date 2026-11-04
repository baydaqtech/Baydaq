<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Models\Customer;
use App\Modules\Platform\Ports\Customers;

class SaveCustomer
{
    public function __construct(private Customers $customers) {}

    /**
     * Save a buyer. One with a phone is linked to the account's shared customer for that phone
     * (created on first use), like a guest at a booking or a member.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->fill($data);

        if ($customer->phone && ($customer->isDirty('phone') || $customer->platform_customer_id === null)) {
            $customer->forceFill(['platform_customer_id' => $this->customers->identify($customer->name_en ?: $customer->name_ar, $customer->phone, $customer->email)?->id]);
        }

        $customer->save();

        return $customer;
    }
}
