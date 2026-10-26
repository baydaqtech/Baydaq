<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersSitePages;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Models\Product;
use App\Models\Service;
use Inertia\Response;

class LandingController extends Controller
{
    use RendersSitePages;

    /**
     * The public home page.
     */
    public function __invoke(): Response
    {
        return $this->sitePage('Landing', [
            'products' => ProductResource::collection(Product::query()->listed()->get()),
            'services' => ServiceResource::collection(Service::query()->listed()->get()),
        ]);
    }
}
