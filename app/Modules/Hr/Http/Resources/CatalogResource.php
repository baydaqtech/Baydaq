<?php

namespace App\Modules\Hr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sections, jobs, allowance types and document types share one shape: a bilingual name plus
 * the model's other attributes, with sections/blocked companies when loaded or computed.
 */
class CatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = collect($this->resource->attributesToArray())->except(['client_id', 'name_ar_key', 'name_en_key', 'created_at', 'updated_at'])->all();

        return [
            ...$attributes,
            'name' => $this->resource->localized('name'),
            'section_ids' => $this->whenLoaded('sections', fn () => $this->resource->sections->pluck('id')->all()),
            'sections' => $this->whenLoaded('sections', fn () => $this->resource->sections->map->localized('name')->all()),
            'blocked_company_ids' => $this->when(method_exists($this->resource, 'blockedCompanyIds'), fn () => $this->resource->blockedCompanyIds()),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
