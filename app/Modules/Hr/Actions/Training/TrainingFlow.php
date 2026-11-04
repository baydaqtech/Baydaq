<?php

namespace App\Modules\Hr\Actions\Training;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Training;
use App\Modules\Hr\Models\TrainingAttendee;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Training: plan a session for a company, enrol employees, mark who attended. An attended session
 * with a validity period gives a certificate that expires (food safety, first aid, forklift...).
 */
class TrainingFlow
{
    public const STATUSES = ['planned', 'done', 'cancelled'];

    public const ATTENDANCE = ['enrolled', 'attended', 'missed'];

    public function __construct(private Audit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Training $training, int $companyId, array $data, ?Authenticatable $actor = null): Training
    {
        $status = $data['status'] ?? null;
        unset($data['status']);
        $training->fill($data);

        if (! $training->exists) {
            $training->forceFill(['company_id' => $companyId, 'status' => 'planned', 'created_by' => $actor?->getAuthIdentifier()]);
        }

        if ($status !== null) {
            $training->forceFill(['status' => $status]);
        }

        $training->save();
        $this->audit->record($training, $training->wasRecentlyCreated ? 'created' : 'updated', null, $actor);

        return $training;
    }

    public function enrol(Training $training, Employee $employee): TrainingAttendee
    {
        if ($employee->company_id !== $training->company_id) {
            throw ValidationException::withMessages(['employee_ids' => __('hr.training.errors.company')]);
        }

        $attendee = TrainingAttendee::query()->where('training_id', $training->id)->where('employee_id', $employee->id)->first();

        return $attendee ?? tap((new TrainingAttendee)->forceFill([
            'client_id' => $training->client_id, 'training_id' => $training->id, 'employee_id' => $employee->id, 'status' => 'enrolled',
        ]))->save();
    }

    public function mark(TrainingAttendee $attendee, string $status, ?string $certificateNumber = null): TrainingAttendee
    {
        $training = $attendee->training;
        $expires = $status === 'attended' && $training->certificate_months
            ? ($training->ends_on ?? $training->starts_on)->copy()->addMonths($training->certificate_months)->toDateString()
            : null;

        $attendee->forceFill(['status' => $status, 'certificate_number' => $status === 'attended' ? $certificateNumber : null, 'certificate_expires_on' => $expires])->save();

        return $attendee;
    }
}
