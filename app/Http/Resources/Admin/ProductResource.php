<?php

namespace App\Http\Resources\Admin;

use App\Actions\Uploads\StoreLogo;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product with both languages, for editing.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'url' => $this->url,
            'logo_url' => StoreLogo::url($this->logo),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
