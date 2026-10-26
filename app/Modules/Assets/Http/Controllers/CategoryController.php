<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Models\Category;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The owner-wide list of asset categories with their default useful life.
 */
class CategoryController extends Controller
{
    use RendersSuitePages;

    public function index(): Response
    {
        return $this->suitePage('Assets/Categories', [
            'categories' => Category::query()->orderBy('name_en')->get()->map(fn (Category $category): array => [...$category->attributesToArray(), 'name' => $category->localized('name')])->all(),
        ], ['assets']);
    }

    public function store(Request $request, CurrentTenant $tenant): RedirectResponse
    {
        (new Category($this->validated($request)))->forceFill(['client_id' => $tenant->client()->id])->save();
        $this->toast(__('assets.categories.saved'));

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->fill($this->validated($request))->save();
        $this->toast(__('assets.categories.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'], 'depreciates' => ['boolean'], 'is_active' => ['boolean'],
        ]);
    }
}
