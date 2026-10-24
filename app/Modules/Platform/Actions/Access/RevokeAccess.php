<?php

namespace App\Modules\Platform\Actions\Access;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Access\AccessAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeAccess
{
    public function __construct(private AccessResolver $resolver, private RecordAuditEvent $audit) {}

    /**
     * Remove one assignment. The last owner of a client can never be removed, so a tenant
     * is never left with nobody able to manage it.
     */
    public function handle(AccessAssignment $assignment, ?User $actor): void
    {
        $assignment->loadMissing('role');

        if ($assignment->role->isOwner()) {
            $owners = AccessAssignment::query()->where('access_role_id', $assignment->access_role_id)->count();

            if ($owners <= 1) {
                throw ValidationException::withMessages(['assignment' => __('suite.access.errors.last_owner')]);
            }
        }

        DB::transaction(function () use ($assignment, $actor): void {
            $this->audit->handle($assignment, 'revoked', ['user_id' => $assignment->user_id, 'role' => $assignment->role->name_en, 'scope' => $assignment->scope_key], $actor);
            $assignment->delete();
        });

        $this->resolver->forget();
    }
}
