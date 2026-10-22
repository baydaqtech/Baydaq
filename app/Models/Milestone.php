<?php

namespace App\Models;

use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A step on a project's development timeline.
 */
#[Fillable(['title', 'description', 'due_date', 'done_at', 'sort_order'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'done_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
