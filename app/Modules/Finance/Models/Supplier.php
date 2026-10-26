<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone the owner buys from, shared by all their companies.
 */
#[Table('finance__suppliers')]
#[Fillable(['name', 'vat_number', 'cr_number', 'phone', 'email', 'iban', 'payment_days', 'is_active'])]
#[Hidden(['iban'])]
class Supplier extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['iban' => 'encrypted', 'is_active' => 'boolean', 'payment_days' => 'integer'];
    }
}
