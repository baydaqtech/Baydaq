<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Models\Concerns\HasAudience;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A policy, circular or news item for the whole account, a company or a branch.
 */
#[Table('hr__announcements')]
#[Fillable(['kind', 'title', 'body', 'requires_ack'])]
class Announcement extends Model
{
    use BelongsToClient, HasAudience;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'requires_ack' => 'boolean'];
    }
}
