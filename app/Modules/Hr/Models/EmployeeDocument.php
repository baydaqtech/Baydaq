<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One document of an employee (an iqama, a degree...) with the type's extra fields in `data`.
 *
 * @property array<string, mixed>|null $data
 * @property list<int>|null $alerted
 */
#[Table('hr__employee_documents')]
#[Fillable(['number', 'issued_on', 'expires_on', 'data', 'notes'])]
#[Hidden(['number'])]
class EmployeeDocument extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'encrypted',
            'issued_on' => 'date',
            'expires_on' => 'date',
            'next_alert_on' => 'date',
            'data' => 'array',
            'alerted' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return HasMany<EmployeeDocumentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(EmployeeDocumentFile::class, 'document_id');
    }

    /**
     * Days before expiry at which to alert next, given the type's thresholds and what was already sent.
     */
    public function scheduleNextAlert(): static
    {
        $thresholds = collect($this->type->alert_days ?? [])->map(fn ($days): int => (int) $days)->sortDesc();
        $pending = $thresholds->reject(fn (int $days): bool => in_array($days, $this->alerted ?? [], true));

        $this->next_alert_on = $this->expires_on && $pending->isNotEmpty()
            ? $this->expires_on->copy()->subDays($pending->first())
            : null;

        return $this;
    }
}
