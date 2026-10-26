<?php

namespace App\Modules\Reservations\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A branch's opening hours (company-local times, Sunday = 0) and online-booking rules.
 */
#[Table('res__branch_settings')]
#[Fillable(['hours', 'slot_minutes', 'min_notice_minutes', 'max_days_ahead', 'is_online'])]
class BranchSettings extends Model
{
    use BelongsToClient;

    protected $primaryKey = 'branch_id';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hours' => 'array', 'slot_minutes' => 'integer', 'min_notice_minutes' => 'integer', 'max_days_ahead' => 'integer', 'is_online' => 'boolean'];
    }

    /**
     * Sunday to Thursday 09:00–22:00, Friday from 16:00, Saturday 09:00–22:00.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public static function defaultHours(): array
    {
        return ['0' => [['09:00', '22:00']], '1' => [['09:00', '22:00']], '2' => [['09:00', '22:00']], '3' => [['09:00', '22:00']], '4' => [['09:00', '22:00']], '5' => [['16:00', '22:00']], '6' => [['09:00', '22:00']]];
    }

    /** The branch's settings, created with the defaults (closed to online booking) on first use. */
    public static function forBranch(int $branchId, int $clientId): self
    {
        return self::query()->find($branchId) ?? tap((new self(['hours' => self::defaultHours()]))->forceFill([
            'branch_id' => $branchId, 'client_id' => $clientId, 'booking_token' => Str::random(32),
        ]))->save();
    }
}
