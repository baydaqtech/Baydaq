<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment link for one payable thing. Looked up by token or provider id (webhooks arrive
 * without a tenant), so it is not tenant-scoped; client_id records whose it is.
 */
#[Table('platform__payments')]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }
}
