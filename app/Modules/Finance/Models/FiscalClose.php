<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A closed year of one company. Written by CloseFiscalYear only.
 */
#[Table('finance__fiscal_closes')]
class FiscalClose extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'profit' => 'decimal:2'];
    }

    /** Whether a date falls in a year the company has closed. */
    public static function covers(int $companyId, string $date): bool
    {
        return self::query()->where('company_id', $companyId)->where('year', '>=', (int) substr($date, 0, 4))->exists();
    }
}
