<?php

namespace App\Modules\Platform\Models\Access;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission key held by a role.
 */
#[Table('platform__access_role_permissions')]
#[Fillable(['permission'])]
class AccessRolePermission extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<AccessRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class, 'access_role_id');
    }
}
