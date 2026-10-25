<?php

namespace App\Modules\Pos\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A till at a branch. It links to the e-invoicing unit that signs its receipts and owns the
 * QR menu token customers scan.
 */
#[Table('pos__registers')]
#[Fillable(['name', 'einvoice_unit_id', 'asset_id', 'require_check_in', 'is_active'])]
class Register extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'require_check_in' => true, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'require_check_in' => 'boolean'];
    }

    /**
     * @return HasOne<Shift, $this>
     */
    public function openShift(): HasOne
    {
        return $this->hasOne(Shift::class)->whereNull('closed_at');
    }
}
