<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform account as the admin sees it (client users and team members).
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'pending' => $this->must_change_password && $this->last_login_at === null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_system_admin' => $this->isSystemAdmin(),
            'permissions' => $this->when($this->relationLoaded('permissions'), fn () => $this->permissions->pluck('name')->values()),
        ];
    }
}
