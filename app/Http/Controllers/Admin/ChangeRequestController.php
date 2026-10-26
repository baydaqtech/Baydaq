<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeRequests\ChangeRequestFlow;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The team answers a client's change request with a quote (B5).
 */
class ChangeRequestController extends Controller
{
    use RendersAdminPages;

    public function quote(Request $request, Project $project, ChangeRequest $changeRequest, ChangeRequestFlow $flow): RedirectResponse
    {
        abort_unless($changeRequest->project_id === $project->id, 404);
        $data = $request->validate([
            'quote_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'quote_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'quote_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $flow->quote($changeRequest, (string) $data['quote_amount'], isset($data['quote_days']) ? (int) $data['quote_days'] : null, $data['quote_note'] ?? null);
        $this->toast(__('admin.projects.changes.quoted'));

        return back();
    }
}
