<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Models\Cashier;
use Illuminate\Support\Facades\Hash;

class SaveCashier
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @param  array{company_id?: int, employee_id?: ?int, name: string, is_active?: bool}  $data
     */
    public function handle(Cashier $cashier, array $data, ?string $pin): Cashier
    {
        $cashier->fill($data);

        if (! $cashier->exists) {
            $cashier->client_id = $this->tenant->client()->id;
        }

        if ($pin !== null && $pin !== '') {
            $cashier->pin_hash = Hash::make($pin);
        }

        $cashier->save();

        return $cashier;
    }

    public static function verify(Cashier $cashier, string $pin): bool
    {
        return $cashier->is_active && Hash::check($pin, $cashier->pin_hash);
    }
}
