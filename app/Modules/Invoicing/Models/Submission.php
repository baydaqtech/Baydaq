<?php

namespace App\Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to ZATCA for an invoice, with the signed XML that was sent (or returned) and the answer.
 */
#[Table('inv__submissions')]
class Submission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['response' => 'array', 'submitted_at' => 'datetime', 'counter' => 'integer'];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
