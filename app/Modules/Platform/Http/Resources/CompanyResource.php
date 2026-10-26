<?php

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'unified_number' => $this->unified_number,
            'gosi_number' => $this->gosi_number,
            'qiwa_id' => $this->qiwa_id,
            'street' => $this->street,
            'building_number' => $this->building_number,
            'district' => $this->district,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'timezone' => $this->timezone,
            'loyalty_percent' => $this->loyalty_percent,
            'client_scope' => $this->client_scope,
            'status' => $this->status,
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
        ];
    }
}
