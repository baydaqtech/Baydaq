<?php

namespace App\Modules\Finance\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * An account in the chart. Accounts with a system key are the ones modules post to.
 */
#[Table('finance__accounts')]
#[Fillable(['code', 'name_ar', 'name_en', 'type', 'is_active'])]
class Account extends Model
{
    use BelongsToClient, HasBilingualFields;

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Debit-natured accounts grow with debits; the rest with credits. */
    public function isDebitNatured(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
