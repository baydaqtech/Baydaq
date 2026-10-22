<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersSitePages;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    use RendersSitePages;

    /**
     * The platform services catalog.
     */
    public function index(): Response
    {
        return $this->sitePage('Services', [
            'services' => ServiceResource::collection(Service::query()->listed()->get()),
        ]);
    }
}
