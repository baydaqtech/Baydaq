<?php

namespace App\Http\Resources\Admin;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
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
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'projects_count' => $this->whenCounted('projects'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
