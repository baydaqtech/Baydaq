<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Services\IssueApiKey;
use App\Actions\Usage\RecordUsage;
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
     * Issue (or rotate) the key Baydaq's service backend reports usage with. Shown once, in the toast.
     */
    public function issueKey(Project $project, ProjectService $projectService, IssueApiKey $issueApiKey): RedirectResponse
    {
        abort_unless($projectService->project_id === $project->id, 404);

        $key = $issueApiKey->handle($projectService);
        $this->toast(__('admin.projects.services.key_issued', ['key' => $key]), 'info');

        return back();
    }

    /**
     * Hand-entered usage, for services whose backend does not report yet.
     */
    public function recordUsage(Request $request, Project $project, ProjectService $projectService, RecordUsage $recordUsage): RedirectResponse
    {
        abort_unless($projectService->project_id === $project->id, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.01'], 'date' => ['required', 'date', 'before_or_equal:today']]);

        $recordUsage->handle($projectService, $data['quantity'], $data['date'], 'manual');
        $this->toast(__('admin.projects.services.usage_recorded'));

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
            'monthly_cap' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pricing_model' => ['required', Rule::enum(PricingModel::class)],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
