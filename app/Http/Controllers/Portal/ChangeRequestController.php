<?php

namespace App\Http\Controllers\Portal;

use App\Actions\ChangeRequests\ChangeRequestFlow;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A client asks for changes on its project and approves or rejects the quote (B5).
 */
class ChangeRequestController extends Controller
{
    use RendersAdminPages;

    public function __construct(private ChangeRequestFlow $flow) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000']]);

        $this->flow->submit($project, $request->user(), $data['title'], $data['description']);
        $this->toast(__('portal.changes.submitted'));

        return back();
    }

    public function decide(Request $request, Project $project, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeFor($project, $changeRequest);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);

        $this->flow->decide($changeRequest, $request->user(), $data['decision'] === 'approve');
        $this->toast(__($data['decision'] === 'approve' ? 'portal.changes.approved' : 'portal.changes.rejected'));

        return back();
    }

    public function withdraw(Project $project, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeFor($project, $changeRequest);

        $this->flow->withdraw($changeRequest);
        $this->toast(__('portal.changes.withdrawn'));

        return back();
    }

    private function authorizeFor(Project $project, ChangeRequest $changeRequest): void
    {
        Gate::authorize('view', $project);
        abort_unless($changeRequest->project_id === $project->id, 404);
    }
}
