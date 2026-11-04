<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersSitePages;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Inertia\Response;

class ProductController extends Controller
{
    use RendersSitePages;

    /**
     * All of Baydaq's products.
     */
    public function index(): Response
    {
        return $this->sitePage('Products', [
            'products' => ProductResource::collection(Product::query()->listed()->get()),
        ]);
    }
}
