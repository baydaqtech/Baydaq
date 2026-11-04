<?php

namespace App\Modules\Hr\Http\Resources\Api;

use App\Modules\Hr\Models\AttendancePunch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendancePunch
 */
class PunchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'punched_at' => $this->punched_at->toIso8601String(),
            'work_date' => $this->work_date->toDateString(),
            'branch_id' => $this->branch_id,
            'work_location_id' => $this->work_location_id,
            'is_remote' => (bool) $this->is_remote,
            'flag' => $this->flag?->value,
            'resolution' => $this->resolution?->value,
        ];
    }
}
