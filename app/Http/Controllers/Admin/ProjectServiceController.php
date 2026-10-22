<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingModel;
use App\Enums\ProjectServiceStatus;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Turns catalog services on for a project, and edits, pauses or resumes them.
 */
class ProjectServiceController extends Controller
{
    use RendersAdminPages;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id'), Rule::unique('project_services')->where('project_id', $project->id)],
            ...$this->rules(),
        ]);

        $project->projectServices()->create([...$validated, 'status' => ProjectServiceStatus::Active]);
        $this->toast(__('admin.projects.services.attached'));

        return back();
    }

    public function update(Request $request, Project $project, ProjectService $projectService): RedirectResponse
    {
        abort_unless($projectService->project_id === $project->id, 404);

        $projectService->update($request->validate([
            ...$this->rules(),
            'status' => ['required', Rule::enum(ProjectServiceStatus::class)],
        ]));
        $this->toast(__('admin.projects.services.updated'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'plan' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pricing_model' => ['required', Rule::enum(PricingModel::class)],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
