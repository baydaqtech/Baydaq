<?php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A pause in a membership. Its days were added to the membership's end date.
 */
#[Table('sub__freezes')]
class Freeze extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'days' => 'integer'];
    }
}
