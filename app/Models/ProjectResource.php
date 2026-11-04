<?php

namespace App\Models;

use Database\Factories\ProjectResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person or resource working on a project (shown on the project page).
 */
#[Fillable(['name', 'role', 'notes'])]
class ProjectResource extends Model
{
    /** @use HasFactory<ProjectResourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
