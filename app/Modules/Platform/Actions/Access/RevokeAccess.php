<?php

namespace App\Modules\Platform\Actions\Access;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeAccess
{
    public function __construct(private AccessResolver $resolver, private RecordAuditEvent $audit) {}

    /**
     * Remove one assignment. The last owner of a client can never be removed, so a tenant
     * is never left with nobody able to manage it.
     */
    public function handle(AccessAssignment $assignment, ?User $actor, bool $guard = true): void
    {
        $assignment->loadMissing('role.permissions');

        if ($guard) {
            $this->guardScope($assignment, $actor);
        }

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

    /**
     * The same rule as giving access: only someone who covers every key of the role at that point
     * of the tree may take it away, and only an owner may remove an owner.
     */
    private function guardScope(AccessAssignment $assignment, ?User $actor): void
    {
        $map = $actor ? $this->resolver->for($actor) : null;

        if ($map?->isOwner()) {
            return;
        }

        $denied = fn () => throw ValidationException::withMessages(['assignment' => __('suite.access.errors.escalation', ['key' => $assignment->role->name_en])]);

        if ($map === null || $assignment->role->isOwner()) {
            $denied();
        }

        foreach ($assignment->role->permissions->pluck('permission') as $key) {
            $reach = $map->branches($key);
            $covered = match ($assignment->level) {
                ScopeLevel::Client, ScopeLevel::Organization => $reach->all,
                ScopeLevel::Company => $reach->allowsWholeCompany((int) $assignment->company_id),
                ScopeLevel::Branch => $reach->allowsBranch((int) $assignment->branch_id, (int) Branch::query()->whereKey($assignment->branch_id)->value('company_id')),
            };

            if (! $covered) {
                $denied();
            }
        }
    }
}
