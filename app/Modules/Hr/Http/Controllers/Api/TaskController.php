<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Tasks\TaskFlow;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\TaskPriority;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\TaskController as WebTaskController;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Task;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The employee app's task screen: my tasks, moving them along (with a photo when asked), and
 * comments. A supervisor whose role can create tasks can also give tasks from the app.
 */
class TaskController extends Controller
{
    use ResolvesEmployee;

    public function __construct(private TaskFlow $flow, private OrgDirectory $directory) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $status = $request->query('status');

        $tasks = Task::query()->with('comments')->where('assignee_id', $employee->id)
            ->when(is_string($status), fn ($query) => $query->where('status', $status), fn ($query) => $query->where(fn ($query) => $query->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])->orWhere('completed_at', '>=', now()->subDays(7))))
            ->orderByRaw("field(status, 'in_progress', 'todo', 'done', 'cancelled')")->orderByRaw('due_at is null')->orderBy('due_at')->limit(100)->get();

        return response()->json(['tasks' => $tasks->map(fn (Task $task): array => $this->present($task))->values()->all()]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->ownTask($request, $task);

        return response()->json($this->present($task->load('comments')));
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $this->ownTask($request, $task);
        $data = $request->validate([
            'status' => ['required', Rule::in([TaskStatus::InProgress->value, TaskStatus::Done->value])],
            'checked' => ['nullable', 'array'],
            'checked.*' => ['integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $task = $this->flow->move($task, TaskStatus::from($data['status']), $request->user(), $request->file('photo'), isset($data['checked']) ? array_map('intval', $data['checked']) : null, $data['note'] ?? null);

        return response()->json($this->present($task->load('comments')));
    }

    public function comment(Request $request, Task $task): JsonResponse
    {
        $this->ownTask($request, $task);
        $comment = $this->flow->comment($task, $request->validate(['body' => ['required', 'string', 'max:2000']])['body'], $request->user());

        return response()->json(['id' => $comment->id], 201);
    }

    /** A supervisor gives a task to someone in their reach, from the app. */
    public function store(Request $request, Access $access): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)], 'assignee_id' => ['required', 'integer'],
            'due_at' => ['nullable', 'date'], 'needs_photo' => ['boolean'],
        ]);
        $assignee = Employee::query()->findOrFail($data['assignee_id']);
        abort_unless($access->allowsBranch(HrFeature::Tasks->key(FeatureAction::Create), $assignee->branch_id, $assignee->company_id, $request->user()), 403);

        $task = $this->flow->save(new Task, [...$data, 'priority' => $data['priority'] ?? 'normal'], $assignee, null, $request->user());

        return response()->json(['id' => $task->id], 201);
    }

    private function ownTask(Request $request, Task $task): void
    {
        abort_unless($task->assignee_id === $this->employee($request)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Task $task): array
    {
        $authors = $task->comments->pluck('user_id')->filter()->unique()->mapWithKeys(fn (int $id): array => [$id => app(Accounts::class)->summary($id)['name'] ?? null])->all();
        $row = WebTaskController::taskArray($task, $this->directory->branch($task->branch_id)?->name, $authors);
        unset($row['assignee_id']);

        return $row;
    }
}
