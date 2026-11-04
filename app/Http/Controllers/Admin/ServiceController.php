<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingModel;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveServiceRequest;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * The platform services catalog.
 */
class ServiceController extends Controller
{
    use RendersAdminPages;

    public function index(): Response
    {
        $services = Service::query()->withCount('projectServices')->orderBy('sort_order')->orderBy('id')->get();

        return $this->adminPage('Admin/Services/Index', ['services' => ServiceResource::collection($services)]);
    }

    public function create(): Response
    {
        return $this->adminPage('Admin/Services/Form', ['service' => null, 'pricingModels' => PricingModel::options()]);
    }

    public function store(SaveServiceRequest $request): RedirectResponse
    {
        Service::create($request->validated());
        $this->toast(__('admin.services.created'));

        return to_route('admin.services.index');
    }

    public function edit(Service $service): Response
    {
        return $this->adminPage('Admin/Services/Form', [
            'service' => new ServiceResource($service),
            'pricingModels' => PricingModel::options(),
        ]);
    }

    public function update(SaveServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());
        $this->toast(__('admin.common.saved'));

        return to_route('admin.services.index');
    }
}
