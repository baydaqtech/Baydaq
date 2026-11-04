<?php

namespace App\Modules\Platform\Models\Access;

use App\Models\User;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Platform\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user holds a role at one point of the tree. Written by AssignAccess with forceFill.
 */
#[Table('platform__access_assignments')]
class AccessAssignment extends Model
{
    use BelongsToClient;

    protected static function booted(): void
    {
        static::saving(function (AccessAssignment $assignment): void {
            $assignment->scope_key = self::scopeKey($assignment->level, $assignment->organization_id, $assignment->company_id, $assignment->branch_id);
        });
    }

    /**
     * One string for the point of the tree: "company:12". Lets a unique index catch duplicates
     * despite the nullable node columns.
     */
    public static function scopeKey(ScopeLevel|string $level, ?int $organizationId, ?int $companyId, ?int $branchId): string
    {
        $level = $level instanceof ScopeLevel ? $level->value : $level;

        return $level.':'.($branchId ?? $companyId ?? $organizationId ?? 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ScopeLevel::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AccessRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class, 'access_role_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
