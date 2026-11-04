<?php

namespace App\Modules\Platform\Actions\Access;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessAssignment;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignAccess
{
    public function __construct(private AccessResolver $resolver, private RecordAuditEvent $audit) {}

    /**
     * Give a user a role at one point of the tree. With $guard on, the actor must already hold
     * every key of the role over that whole scope, so nobody can grant more than they have.
     */
    public function handle(
        User $user,
        AccessRole $role,
        ScopeLevel $level,
        Organization|Company|Branch|null $node,
        ?User $actor,
        bool $guard = true,
    ): AccessAssignment {
        if ($user->client_id !== $role->client_id) {
            throw ValidationException::withMessages(['user' => __('suite.access.errors.other_tenant')]);
        }

        if (($level === ScopeLevel::Client) !== ($node === null) || ($node !== null && ! $this->matches($level, $node))) {
            throw ValidationException::withMessages(['scope' => __('suite.access.errors.scope_mismatch')]);
        }

        if ($guard) {
            $this->guardEscalation($role, $level, $node, $actor);
        }

        return DB::transaction(function () use ($user, $role, $level, $node, $actor): AccessAssignment {
            $attributes = [
                'user_id' => $user->id,
                'access_role_id' => $role->id,
                'level' => $level->value,
                'organization_id' => $node instanceof Organization ? $node->id : null,
                'company_id' => $node instanceof Company ? $node->id : null,
                'branch_id' => $node instanceof Branch ? $node->id : null,
            ];
            $assignment = AccessAssignment::query()->where($attributes)->first();

            if ($assignment === null) {
                // scope_key is set here as well as in the model hook: seeders run without model events.
                $assignment = (new AccessAssignment)->forceFill([
                    ...$attributes,
                    'client_id' => $role->client_id,
                    'scope_key' => AccessAssignment::scopeKey($level, $attributes['organization_id'], $attributes['company_id'], $attributes['branch_id']),
                ]);
                $assignment->save();
                $this->audit->handle($assignment, 'granted', ['user_id' => $user->id, 'role' => $role->name_en, 'scope' => $assignment->scope_key], $actor);
                $this->resolver->forget($user);
            }

            return $assignment;
        });
    }

    private function matches(ScopeLevel $level, Model $node): bool
    {
        return match ($level) {
            ScopeLevel::Organization => $node instanceof Organization,
            ScopeLevel::Company => $node instanceof Company,
            ScopeLevel::Branch => $node instanceof Branch,
            ScopeLevel::Client => false,
        };
    }

    private function guardEscalation(AccessRole $role, ScopeLevel $level, Organization|Company|Branch|null $node, ?User $actor): void
    {
        if ($actor === null) {
            throw ValidationException::withMessages(['scope' => __('suite.access.errors.escalation', ['key' => $role->name_en])]);
        }

        $map = $this->resolver->for($actor);

        if ($map->isOwner()) {
            return;
        }

        foreach ($role->permissions->pluck('permission') as $key) {
            $reach = $map->branches($key);
            $covered = match ($level) {
                ScopeLevel::Client => $reach->all,
                ScopeLevel::Organization => $reach->all,
                ScopeLevel::Company => $reach->allowsWholeCompany($node->id),
                ScopeLevel::Branch => $reach->allowsBranch($node->id, $node->company_id),
            };

            if (! $covered) {
                throw ValidationException::withMessages(['permissions' => __('suite.access.errors.escalation', ['key' => $key])]);
            }
        }
    }
}
