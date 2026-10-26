<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Models\Concerns\HasAudience;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A survey for staff, anonymous or named.
 */
#[Table('hr__surveys')]
#[Fillable(['title', 'description', 'questions', 'anonymous', 'closes_on'])]
class Survey extends Model
{
    use BelongsToClient, HasAudience;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['questions' => 'array', 'anonymous' => 'boolean', 'closes_on' => 'date'];
    }
}
