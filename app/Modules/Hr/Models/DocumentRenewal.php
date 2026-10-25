<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A to-do to renew one document before it expires; one per document and expiry date.
 */
#[Table('hr__document_renewals')]
class DocumentRenewal extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_on' => 'date', 'cost' => 'decimal:2', 'done_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<EmployeeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }

    /** Open a renewal task for this expiry, once. */
    public static function openFor(EmployeeDocument $document): self
    {
        $expiresOn = $document->expires_on->toDateString();

        return self::query()->where('employee_document_id', $document->id)->whereDate('expires_on', $expiresOn)->first()
            ?? tap((new self)->forceFill([
                'client_id' => $document->client_id, 'employee_document_id' => $document->id, 'expires_on' => $expiresOn,
                'cost' => $document->type->renewal_cost, 'status' => 'open',
            ]))->save();
    }
}
