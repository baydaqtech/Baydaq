<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's request about their personal data under the PDPL, answered within 30 days.
 */
#[Table('platform__privacy_requests')]
#[Fillable(['kind', 'subject', 'customer_id', 'name', 'contact', 'received_on', 'details'])]
class PrivacyRequest extends Model
{
    use BelongsToClient;

    public const KINDS = ['access', 'correction', 'erasure', 'objection'];

    public const SUBJECTS = ['customer', 'employee', 'other'];

    public const ANSWER_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['received_on' => 'date', 'due_on' => 'date', 'closed_at' => 'datetime'];
    }
}
