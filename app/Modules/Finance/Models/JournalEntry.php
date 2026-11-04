<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A balanced journal entry. Written by the ledger only; corrected by a reversal, never edited.
 */
#[Table('finance__journal_entries')]
class JournalEntry extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'is_reversal' => 'boolean'];
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'entry_id');
    }
}
