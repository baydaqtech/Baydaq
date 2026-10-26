<?php

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\Access\AccessRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessRole
 */
class AccessRoleResource extends JsonResource
{
    /**
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
            'is_locked' => $this->is_locked,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('permission')->values()->all()),
            'assignments_count' => $this->whenCounted('assignments'),
        ];
    }
}
