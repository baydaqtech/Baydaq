<?php

namespace App\Modules\Pos\Models;

use App\Modules\Pos\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('pos__payments')]
class Payment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }
}
