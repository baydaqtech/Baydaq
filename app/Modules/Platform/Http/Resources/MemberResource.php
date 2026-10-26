<?php

namespace App\Modules\Platform\Http\Resources;

use App\Models\User;
use App\Modules\Platform\Models\Access\AccessAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person of the client with the roles they hold and where.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'pending' => $this->must_change_password,
            'last_login_at' => $this->last_login_at?->toDateString(),
            'assignments' => $this->whenLoaded('accessAssignments', fn () => $this->accessAssignments->map(fn (AccessAssignment $assignment): array => [
                'id' => $assignment->id,
                'role' => $assignment->role->localized('name'),
                'is_owner' => $assignment->role->isOwner(),
                'level' => $assignment->level->value,
                'node' => match ($assignment->level->value) {
                    'organization' => $assignment->organization?->localized('name'),
                    'company' => $assignment->company?->localized('name'),
                    'branch' => $assignment->branch?->localized('name'),
                    default => null,
                },
            ])->values()->all()),
        ];
    }
}
