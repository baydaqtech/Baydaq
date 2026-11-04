<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Uploads\StoreLogo;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Baydaq's products, shown on the public site.
 */
class ProductController extends Controller
{
    use RendersAdminPages;

    public function index(): Response
    {
        $products = Product::query()->orderBy('sort_order')->orderBy('id')->get();

        return $this->adminPage('Admin/Products/Index', ['products' => ProductResource::collection($products)]);
    }

    public function create(): Response
    {
        return $this->adminPage('Admin/Products/Form', ['product' => null]);
    }

    public function store(SaveProductRequest $request, StoreLogo $storeLogo): RedirectResponse
    {
        $product = new Product($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $product->logo = $storeLogo->handle($request->file('logo'));
        }

        $product->save();
        $this->toast(__('admin.products.created'));

        return to_route('admin.products.index');
    }

    public function edit(Product $product): Response
    {
        return $this->adminPage('Admin/Products/Form', ['product' => new ProductResource($product)]);
    }

    public function update(SaveProductRequest $request, Product $product, StoreLogo $storeLogo): RedirectResponse
    {
        $product->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $product->logo = $storeLogo->handle($request->file('logo'), $product->logo);
        }

        $product->save();
        $this->toast(__('admin.common.saved'));

        return to_route('admin.products.index');
    }
}
