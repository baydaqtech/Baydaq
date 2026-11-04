<?php

namespace App\Http\Resources\Admin;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog entry with both languages, for editing.
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
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
            'key' => $this->key,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'docs_url' => $this->docs_url,
            'default_price' => $this->default_price,
            'pricing_model' => $this->pricing_model->value,
            'unit' => $this->unit,
            'projects_count' => $this->whenCounted('projectServices'),
        ];
    }
}
