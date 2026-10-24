<?php

namespace App\Modules\Platform\Models\Access;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Database\Factories\AccessRoleFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bundle of permission keys a tenant defines ("HR Manager", "Branch Viewer").
 * The built-in owner role has key "owner", permission "*" and is locked.
 */
#[Table('platform__access_roles')]
#[Fillable(['name_ar', 'name_en'])]
class AccessRole extends Model
{
    /** @use HasFactory<AccessRoleFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    public const OWNER_KEY = 'owner';

    public const WILDCARD = '*';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_locked' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AccessRolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(AccessRolePermission::class);
    }

    /**
     * @return HasMany<AccessAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AccessAssignment::class);
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER_KEY;
    }

    protected static function newFactory(): AccessRoleFactory
    {
        return AccessRoleFactory::new();
    }
}
