<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Usage\UsageSummary;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\InvoiceResource;
use App\Http\Resources\Portal\ProjectResource;
use App\Http\Resources\Portal\ProjectServiceResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * One project: overview, files, services and billing tabs.
     */
    public function show(Request $request, Project $project, UsageSummary $usage): Response
    {
        Gate::authorize('view', $project);

        $project->load(['resources', 'files', 'milestones', 'releases', 'changeRequests', 'projectServices.service']);
        $invoices = $project->invoices()->issued()->latest('issue_date')->get();

        return Inertia::render('Portal/Project', [
            'project' => new ProjectResource($project),
            'services' => $project->projectServices->map(fn ($projectService) => (new ProjectServiceResource($projectService))->additional(['usage' => $usage->handle($projectService)])->resolve() + ['usage' => $usage->handle($projectService)])->values()->all(),
            'invoices' => InvoiceResource::collection($invoices),
            'billing' => [
                'due' => (string) $invoices->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->sum('total'),
                'overdue' => (string) $invoices->where('status', InvoiceStatus::Overdue)->sum('total'),
            ],
            'tab' => $request->query('tab', 'overview'),
            'copy' => ['portal' => trans('portal')],
        ]);
    }
}
