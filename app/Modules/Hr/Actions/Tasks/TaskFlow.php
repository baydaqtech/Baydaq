<?php

namespace App\Modules\Hr\Actions\Tasks;

use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Task;
use App\Modules\Hr\Models\TaskComment;
use App\Modules\Hr\Support\EmployeeMessenger;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tasks for employees: a manager creates and assigns them, the employee starts and finishes them
 * from the app (with a photo when the task asks for one), and both talk in the comments.
 */
class TaskFlow
{
    public function __construct(private OrgDirectory $directory, private EmployeeMessenger $messenger) {}

    /**
     * @param  array{title: string, description?: ?string, priority?: string, checklist?: ?list<array{text: string, done?: bool}>, needs_photo?: bool, due_at?: ?string}  $data
     */
    public function save(Task $task, array $data, ?Employee $assignee, ?int $branchId, ?Authenticatable $actor): Task
    {
        $branchId = $assignee?->branch_id ?? $branchId;
        $branch = $branchId ? $this->directory->branch($branchId) : null;

        if ($branch === null) {
            throw ValidationException::withMessages(['branch_id' => __('hr.tasks.errors.branch')]);
        }

        $newAssignee = $assignee !== null && $assignee->id !== $task->assignee_id;
        $task->fill([...Arr::only($data, ['title', 'description', 'priority', 'needs_photo', 'due_at']), 'checklist' => $this->checklist($data['checklist'] ?? $task->checklist ?? [])]);
        $task->forceFill(['company_id' => $branch->companyId, 'branch_id' => $branch->id, 'assignee_id' => $assignee?->id]);

        if (! $task->exists) {
            $task->forceFill(['created_by' => $actor?->getAuthIdentifier()]);
        }

        $task->save();

        if ($newAssignee) {
            $this->messenger->send($assignee, 'hr_task_assigned', [
                'title' => $task->title, 'due' => $task->due_at?->timezone('Asia/Riyadh')->format('Y-m-d H:i') ?? '—',
            ], "hr.task:{$task->id}");
        }

        return $task;
    }

    /**
     * Move a task along. Done needs every checklist item ticked and, when asked for, a photo.
     *
     * @param  list<int>|null  $checked  indexes of ticked checklist items, as sent from the app
     */
    public function move(Task $task, TaskStatus $to, ?Authenticatable $actor, ?UploadedFile $photo = null, ?array $checked = null, ?string $note = null): Task
    {
        if (in_array($task->status, [TaskStatus::Done, TaskStatus::Cancelled], true) && $to !== TaskStatus::Todo) {
            throw ValidationException::withMessages(['status' => __('hr.tasks.errors.closed')]);
        }

        return DB::transaction(function () use ($task, $to, $actor, $photo, $checked, $note): Task {
            if ($checked !== null) {
                $task->checklist = array_map(fn (array $item, int $index): array => ['text' => $item['text'], 'done' => in_array($index, $checked, true)], $task->checklist ?? [], array_keys($task->checklist ?? []));
            }

            if ($photo !== null) {
                $task->photo_path = $photo->store("tasks/{$task->client_id}", 'hr');
            }

            if ($to === TaskStatus::Done) {
                if (collect($task->checklist ?? [])->contains(fn (array $item): bool => empty($item['done']))) {
                    throw ValidationException::withMessages(['checklist' => __('hr.tasks.errors.checklist')]);
                }

                if ($task->needs_photo && $task->photo_path === null) {
                    throw ValidationException::withMessages(['photo' => __('hr.tasks.errors.photo')]);
                }
            }

            $task->forceFill([
                'status' => $to,
                'started_at' => $to === TaskStatus::InProgress ? ($task->started_at ?? now()) : $task->started_at,
                'completed_at' => $to === TaskStatus::Done ? now() : null,
            ])->save();

            if ($note !== null && trim($note) !== '') {
                $this->comment($task, $note, $actor);
            }

            return $task;
        });
    }

    public function comment(Task $task, string $body, ?Authenticatable $actor): TaskComment
    {
        $comment = $task->comments()->make(['body' => trim($body)]);
        $comment->forceFill(['user_id' => $actor?->getAuthIdentifier()])->save();

        return $comment;
    }

    /**
     * @param  list<array{text?: ?string, done?: bool}>  $items
     * @return list<array{text: string, done: bool}>|null
     */
    private function checklist(array $items): ?array
    {
        $clean = array_values(array_filter(array_map(fn (array $item): array => ['text' => trim((string) ($item['text'] ?? '')), 'done' => (bool) ($item['done'] ?? false)], $items), fn (array $item): bool => $item['text'] !== ''));

        return $clean === [] ? null : $clean;
    }
}
