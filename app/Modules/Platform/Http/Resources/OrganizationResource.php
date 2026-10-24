<?php

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
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
            'status' => $this->status,
            'companies' => CompanyResource::collection($this->whenLoaded('companies')),
        ];
    }
}
