<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdjustmentStatus;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class DecideAdjustment
{
    public function __construct(private Audit $audit) {}

    /**
     * Approve or reject a pending bonus or deduction. Paid ones can't change.
     */
    public function handle(Bonus|Deduction $adjustment, bool $approve, Authenticatable $actor): Bonus|Deduction
    {
        if ($adjustment->status !== AdjustmentStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('hr.pay.errors.not_pending')]);
        }

        $adjustment->forceFill([
            'status' => ($approve ? AdjustmentStatus::Approved : AdjustmentStatus::Rejected)->value,
            'approved_by' => $actor->getAuthIdentifier(),
        ])->save();

        $this->audit->record($adjustment, $approve ? 'approved' : 'rejected', null, $actor);

        return $adjustment;
    }
}
