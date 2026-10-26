<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to an employee document, on the private HR disk; downloads go through a
 * short-lived signed URL.
 */
#[Table('hr__employee_document_files')]
class EmployeeDocumentFile extends Model
{
    /**
     * @return BelongsTo<EmployeeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    public function temporaryUrl(int $minutes = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }
}
