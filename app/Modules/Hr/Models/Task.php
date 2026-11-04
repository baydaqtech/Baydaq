<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\TaskPriority;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A piece of work given to an employee (or left open at a branch), followed up from the app.
 * Status changes go through TaskFlow.
 */
#[Table('hr__tasks')]
#[Fillable(['title', 'description', 'priority', 'checklist', 'needs_photo', 'due_at'])]
class Task extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'todo', 'priority' => 'normal', 'needs_photo' => false];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class, 'priority' => TaskPriority::class, 'checklist' => 'array', 'needs_photo' => 'boolean',
            'due_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && in_array($this->status, [TaskStatus::Todo, TaskStatus::InProgress], true);
    }
}
