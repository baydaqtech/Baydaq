<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Models\Customer;

class SaveCustomer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->fill($data)->save();

        return $customer;
    }
}
