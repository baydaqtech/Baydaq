<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Tasks\TaskFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\TaskPriority;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Task;
use App\Modules\Hr\Models\TaskComment;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The task board: tasks per branch in columns by status, with filters, assignment and comments.
 */
class TaskController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function __construct(private OrgDirectory $directory, private TaskFlow $flow) {}

    public function index(Request $request, Accounts $accounts): Response
    {
        $reach = $this->reach(HrFeature::Tasks, FeatureAction::View);
        $filters = $request->only(['assignee_id', 'branch_id', 'priority', 'mine']);

        $tasks = $reach->apply(Task::query())->with(['assignee:id,name_ar,name_en', 'comments'])
            ->where(fn ($query) => $query->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])->orWhere('updated_at', '>=', now()->subDays(14)))
            ->when($filters['assignee_id'] ?? null, fn ($query, $id) => $query->where('assignee_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['mine'] ?? null, fn ($query) => $query->where('created_by', $request->user()->id))
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")->orderByRaw('due_at is null')->orderBy('due_at')
            ->limit(300)->get();

        $branches = collect($this->directory->branches())->filter(fn (BranchRef $branch): bool => $branch->isActive && $reach->allowsBranch($branch->id, $branch->companyId));
        $authors = $tasks->flatMap->comments->pluck('user_id')->filter()->unique()->mapWithKeys(fn (int $id): array => [$id => $accounts->summary($id)['name'] ?? null]);

        return $this->suitePage('Hr/Tasks/Index', [
            'tasks' => $tasks->map(fn (Task $task): array => self::taskArray($task, $branches->firstWhere('id', $task->branch_id)?->name, $authors->all()))->values()->all(),
            'filters' => $filters,
            'branches' => $branches->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name])->values()->all(),
            'employees' => $this->employees()->where('status', EmployeeStatus::Active)->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'branch_id'])
                ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->localized('name'), 'branch_id' => $employee->branch_id])->all(),
            'priorities' => TaskPriority::options(),
            'statuses' => TaskStatus::options(),
        ], ['hr']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        [$assignee, $branchId] = $this->placement($data, FeatureAction::Create);
        $this->flow->save(new Task, $data, $assignee, $branchId, $request->user());
        $this->toast(__('hr.tasks.saved'));

        return back();
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task, FeatureAction::Update);
        $data = $this->validated($request);
        [$assignee, $branchId] = $this->placement($data, FeatureAction::Update);
        $this->flow->save($task, $data, $assignee, $branchId, $request->user());
        $this->toast(__('hr.tasks.saved'));

        return back();
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task, FeatureAction::Update);
        $data = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->flow->move($task, TaskStatus::from($data['status']), $request->user(), null, null, $data['note'] ?? null);

        return back();
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($task, FeatureAction::View);
        $this->flow->comment($task, $request->validate(['body' => ['required', 'string', 'max:2000']])['body'], $request->user());

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTask($task, FeatureAction::Delete);

        if ($task->photo_path) {
            Storage::disk('hr')->delete($task->photo_path);
        }

        $task->delete();
        $this->toast(__('hr.tasks.deleted'));

        return back();
    }

    public function photo(Task $task): StreamedResponse
    {
        $this->authorizeTask($task, FeatureAction::View);
        abort_unless($task->photo_path !== null, 404);

        return Storage::disk('hr')->response($task->photo_path);
    }

    /**
     * @param  array<int, string|null>  $authors  user id => name
     * @return array<string, mixed>
     */
    public static function taskArray(Task $task, ?string $branch, array $authors = []): array
    {
        return [
            'id' => $task->id, 'title' => $task->title, 'description' => $task->description,
            'status' => $task->status->value, 'priority' => $task->priority->value,
            'assignee_id' => $task->assignee_id, 'assignee' => $task->assignee?->localized('name'),
            'branch_id' => $task->branch_id, 'branch' => $branch,
            'checklist' => $task->checklist ?? [], 'needs_photo' => $task->needs_photo, 'has_photo' => $task->photo_path !== null,
            'due_at' => $task->due_at?->toIso8601String(), 'overdue' => $task->isOverdue(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'comments' => $task->relationLoaded('comments') ? $task->comments->map(fn (TaskComment $comment): array => [
                'id' => $comment->id, 'body' => $comment->body, 'author' => $authors[$comment->user_id] ?? null, 'created_at' => $comment->created_at->toIso8601String(),
            ])->all() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'needs_photo' => ['boolean'],
            'checklist' => ['nullable', 'array', 'max:30'],
            'checklist.*.text' => ['nullable', 'string', 'max:200'],
            'checklist.*.done' => ['boolean'],
        ]);
    }

    /**
     * The assignee (who must be in reach) or, for an unassigned task, the branch it belongs to.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?Employee, 1: ?int}
     */
    private function placement(array $data, FeatureAction $action): array
    {
        $reach = $this->reach(HrFeature::Tasks, $action);

        if (! empty($data['assignee_id'])) {
            $assignee = Employee::query()->findOrFail($data['assignee_id']);
            abort_unless($reach->allowsBranch($assignee->branch_id, $assignee->company_id), 403);

            return [$assignee, null];
        }

        $branch = isset($data['branch_id']) ? $this->directory->branch((int) $data['branch_id']) : null;
        abort_unless($branch !== null && $reach->allowsBranch($branch->id, $branch->companyId), 403);

        return [null, $branch->id];
    }

    private function authorizeTask(Task $task, FeatureAction $action): void
    {
        abort_unless($this->reach(HrFeature::Tasks, $action)->allowsBranch($task->branch_id, $task->company_id), 403);
    }
}
