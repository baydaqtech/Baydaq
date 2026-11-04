<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The client sets the monthly spend it wants to be warned about for a service (B2).
 */
class SpendCapController extends Controller
{
    use RendersAdminPages;

    public function __invoke(Request $request, Project $project, ProjectService $projectService): RedirectResponse
    {
        Gate::authorize('view', $project);
        abort_unless($projectService->project_id === $project->id, 404);
        $data = $request->validate(['monthly_cap' => ['nullable', 'numeric', 'min:0', 'max:9999999999']]);

        $projectService->forceFill(['monthly_cap' => $data['monthly_cap'] ?? null, 'cap_alert_month' => null, 'cap_alert_level' => null])->save();
        $this->toast(__('portal.caps.saved'));

        return back();
    }
}
