<?php

namespace App\Modules\Platform\Actions\Access;

use App\Models\User;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\FeatureRegistry;
use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Access\AccessRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAccessRole
{
    public function __construct(
        private FeatureRegistry $registry,
        private AccessResolver $resolver,
        private RecordAuditEvent $audit,
    ) {}

    /**
     * Create or update a tenant role with its permission keys. Keys must exist in the registry,
     * and the actor may only hand out keys they hold themselves (no escalation).
     *
     * @param  array{name_ar: string, name_en: string}  $data
     * @param  list<string>  $permissions
     */
    public function handle(AccessRole $role, array $data, array $permissions, User $actor): AccessRole
    {
        if ($role->is_locked) {
            throw ValidationException::withMessages(['role' => __('suite.access.errors.locked')]);
        }

        $permissions = array_values(array_unique($permissions));
        $unknown = array_filter($permissions, fn (string $key): bool => ! $this->registry->has($key));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => __('suite.access.errors.unknown_permission', ['key' => reset($unknown)])]);
        }

        $map = $this->resolver->for($actor);
        $escalating = array_filter($permissions, fn (string $key): bool => ! $map->allows($key));

        if ($escalating !== []) {
            throw ValidationException::withMessages(['permissions' => __('suite.access.errors.escalation', ['key' => reset($escalating)])]);
        }

        return DB::transaction(function () use ($role, $data, $permissions, $actor): AccessRole {
            $role->fill($data)->save();
            $role->permissions()->delete();
            $role->permissions()->createMany(array_map(fn (string $key): array => ['permission' => $key], $permissions));

            $this->audit->handle($role, $role->wasRecentlyCreated ? 'created' : 'updated', ['permissions' => $permissions], $actor);

            return $role->load('permissions');
        });
    }
}
