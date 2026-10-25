<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who applied for an opening, moved through the hiring stages.
 */
#[Table('hr__candidates')]
#[Fillable(['name', 'phone', 'email', 'nationality', 'rating', 'notes', 'interview_at', 'offer_salary'])]
class Candidate extends Model
{
    use BelongsToClient;

    public const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['interview_at' => 'datetime', 'offer_salary' => 'decimal:2', 'consent_at' => 'datetime', 'rating' => 'integer'];
    }

    /**
     * @return BelongsTo<JobOpening, $this>
     */
    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'opening_id');
    }
}
