<?php

namespace App\Modules\Hr\Actions\Attendance;

use App\Modules\Hr\Enums\PunchResolution;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class ResolvePunchFlag
{
    public function __construct(private Audit $audit) {}

    /**
     * A manager with resolve_flags accepts or rejects a flagged punch, with a note.
     */
    public function handle(AttendancePunch $punch, PunchResolution $resolution, ?string $note, Authenticatable $actor): AttendancePunch
    {
        if ($punch->flag === null) {
            throw ValidationException::withMessages(['punch' => __('hr.attendance.errors.not_flagged')]);
        }

        $punch->forceFill([
            'resolution' => $resolution->value,
            'resolution_note' => $note,
            'resolved_by' => $actor->getAuthIdentifier(),
            'resolved_at' => now(),
        ])->save();

        $this->audit->record($punch, 'flag_resolved', ['resolution' => $resolution->value, 'note' => $note], $actor);

        return $punch;
    }
}
