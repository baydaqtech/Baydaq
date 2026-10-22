<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\SyncProjectItems;
use App\Enums\FileCategory;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves a project's milestones, team and file links, each as one list.
 */
class ProjectItemsController extends Controller
{
    use RendersAdminPages;

    public function __construct(private SyncProjectItems $syncProjectItems) {}

    public function milestones(Request $request, Project $project): RedirectResponse
    {
        $rows = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.due_date' => ['nullable', 'date'],
            'items.*.done' => ['boolean'],
        ])['items'];

        $rows = array_map(fn (array $row, int $index): array => [
            ...$row,
            'sort_order' => $index + 1,
            'done_at' => ! empty($row['done']) ? ($project->milestones()->whereKey($row['id'] ?? 0)->value('done_at') ?? now()) : null,
        ], $rows, array_keys($rows));

        $this->syncProjectItems->handle($project->milestones(), $rows, ['title', 'description', 'due_date', 'done_at', 'sort_order']);

        return $this->saved();
    }

    public function resources(Request $request, Project $project): RedirectResponse
    {
        $rows = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.role' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ])['items'];

        $this->syncProjectItems->handle($project->resources(), $rows, ['name', 'role', 'notes']);

        return $this->saved();
    }

    public function files(Request $request, Project $project): RedirectResponse
    {
        $rows = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.url' => ['required', 'url', 'max:2048'],
            'items.*.category' => ['required', Rule::enum(FileCategory::class)],
        ])['items'];

        $this->syncProjectItems->handle($project->files(), $rows, ['title', 'url', 'category']);

        return $this->saved();
    }

    private function saved(): RedirectResponse
    {
        $this->toast(__('admin.common.saved'));

        return back();
    }
}
