<?php

namespace App\Modules\Hr\Models;

use App\Modules\Hr\Enums\LetterKind;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A salary or experience letter as issued. Created by IssueLetter only; never edited, only revoked.
 */
#[Table('hr__letters')]
class Letter extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => LetterKind::class, 'snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }
}
