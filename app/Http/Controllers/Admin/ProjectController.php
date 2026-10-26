<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Uploads\StoreLogo;
use App\Enums\FileCategory;
use App\Enums\PricingModel;
use App\Enums\ProjectRelationship;
use App\Enums\ProjectServiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\ReleaseChannel;
use App\Enums\ReleasePlatform;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProjectRequest;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProjectController extends Controller
{
    use RendersAdminPages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'client_id', 'status', 'type']);

        $projects = Project::query()
            ->with('client')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereAny(['name_ar', 'name_en', 'slug'], 'like', "%{$search}%"),
            ))
            ->when($filters['client_id'] ?? null, fn (Builder $query, string $clientId) => $query->where('client_id', $clientId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return $this->adminPage('Admin/Projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->adminPage('Admin/Projects/Form', [
            'project' => null,
            'clientId' => $request->integer('client_id') ?: null,
            'options' => $this->options(),
        ]);
    }

    public function store(SaveProjectRequest $request, StoreLogo $storeLogo): RedirectResponse
    {
        $project = new Project($request->projectData());

        if ($request->hasFile('logo')) {
            $project->logo = $storeLogo->handle($request->file('logo'));
        }

        $project->save();
        $this->toast(__('admin.projects.created'));

        return to_route('admin.projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load(['client', 'milestones', 'resources', 'files', 'releases', 'changeRequests.user', 'projectServices.service']);
        $attached = $project->projectServices->pluck('service_id');

        return $this->adminPage('Admin/Projects/Show', [
            'project' => new ProjectResource($project),
            'tab' => $request->query('tab', 'overview'),
            'availableServices' => Service::query()
                ->whereNotIn('id', $attached)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Service $service): array => [
                    'value' => $service->id,
                    'label' => $service->localized('name'),
                    'pricing_model' => $service->pricing_model->value,
                    'default_price' => $service->default_price,
                ]),
            'options' => [
                ...$this->options(),
                'fileCategories' => FileCategory::options(),
                'pricingModels' => PricingModel::options(),
                'serviceStatuses' => ProjectServiceStatus::options(),
                'releasePlatforms' => ReleasePlatform::options(),
                'releaseChannels' => ReleaseChannel::options(),
            ],
        ]);
    }

    public function edit(Project $project): Response
    {
        return $this->adminPage('Admin/Projects/Form', [
            'project' => new ProjectResource($project),
            'clientId' => $project->client_id,
            'options' => $this->options(),
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project, StoreLogo $storeLogo): RedirectResponse
    {
        $project->fill($request->projectData());

        if ($request->hasFile('logo')) {
            $project->logo = $storeLogo->handle($request->file('logo'), $project->logo);
        }

        $project->save();
        $this->toast(__('admin.common.saved'));

        return to_route('admin.projects.show', $project);
    }

    /**
     * Choices for the project selects and filters.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'clients' => Client::query()->orderBy('name_en')->get()
                ->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->localized('name')]),
            'types' => ProjectType::options(),
            'relationships' => ProjectRelationship::options(),
            'statuses' => ProjectStatus::options(),
        ];
    }
}
