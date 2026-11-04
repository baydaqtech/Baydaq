<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shift at a branch on a day. With no employee and is_open on, anyone eligible may pick
 * it up; picking up a shift away from home creates a one-day secondment so the punch there
 * isn't flagged.
 */
#[Table('hr__shifts')]
class Shift extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shift_date' => 'date', 'is_open' => 'boolean', 'on_holiday' => 'boolean', 'ramadan_hours' => 'boolean', 'minutes' => 'integer'];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleIn(Builder $query, BranchSet $branches): void
    {
        $branches->apply($query, $this->qualifyColumn('company_id'), $this->qualifyColumn('branch_id'));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
