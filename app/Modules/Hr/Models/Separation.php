<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee leaving: the notice, the decision and the exit checklist. Changed by SeparationFlow only.
 */
#[Table('hr__separations')]
class Separation extends Model
{
    use BelongsToClient;

    public const KINDS = ['resignation', 'termination', 'contract_end', 'retirement', 'mutual'];

    /** Statuses still in progress: one per employee at a time. */
    public const OPEN = ['submitted', 'deferred', 'accepted'];

    /** The exit checklist; `assets` is answered by the modules that lent the employee something. */
    public const CHECKLIST = ['handover', 'assets', 'access', 'settlement', 'experience_letter', 'qiwa', 'gosi', 'muqeem', 'exit_interview'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_on' => 'date', 'last_day' => 'date', 'deemed_accept_on' => 'date', 'deemed' => 'boolean',
            'checklist' => 'array', 'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
