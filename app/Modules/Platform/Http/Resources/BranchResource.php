<?php

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'city' => $this->localized('city'),
            'city_ar' => $this->city_ar,
            'city_en' => $this->city_en,
            'is_head_office' => $this->is_head_office,
            'status' => $this->status,
        ];
    }
}
