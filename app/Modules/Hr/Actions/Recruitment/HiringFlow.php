<?php

namespace App\Modules\Hr\Actions\Recruitment;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Actions\Tasks\TaskFlow;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\Task;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Ports\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * From application to first day: candidates move through the stages, and hiring one creates the
 * employee (with a contract at the offered salary) and gives them their onboarding tasks.
 */
class HiringFlow
{
    public function __construct(private SaveEmployee $saveEmployee, private SaveContract $saveContract, private TaskFlow $tasks, private HrSettings $settings, private Audit $audit) {}

    public function open(JobOpening $opening, array $data, int $companyId, int $branchId, ?Authenticatable $actor = null): JobOpening
    {
        $opening->fill($data);

        if (! $opening->exists) {
            $opening->forceFill(['company_id' => $companyId, 'branch_id' => $branchId, 'token' => Str::random(32), 'created_by' => $actor?->getAuthIdentifier()]);
        }

        $opening->save();

        return $opening;
    }

    /**
     * @param  array{name: string, phone: string, email?: ?string, nationality?: ?string, notes?: ?string}  $data
     */
    public function apply(JobOpening $opening, array $data, ?UploadedFile $cv, string $source): Candidate
    {
        if ($source === 'careers' && ! $opening->isAccepting()) {
            throw ValidationException::withMessages(['name' => __('hr.recruitment.errors.closed')]);
        }

        $candidate = new Candidate($data);
        $candidate->forceFill([
            'opening_id' => $opening->id,
            'client_id' => $opening->client_id,
            'source' => $source,
            'consent_at' => now(),
            'cv_path' => $cv?->store("cv/{$opening->client_id}", config('baydaq.hr.disk')),
        ])->save();

        return $candidate;
    }

    /**
     * @param  array{stage?: string, rating?: ?int, notes?: ?string, interview_at?: ?string, offer_salary?: ?string}  $data
     */
    public function update(Candidate $candidate, array $data, ?Authenticatable $actor = null): Candidate
    {
        if ($candidate->stage === 'hired') {
            throw ValidationException::withMessages(['stage' => __('hr.recruitment.errors.hired')]);
        }

        if (($data['stage'] ?? null) === 'hired') {
            unset($data['stage']); // only hire() makes someone an employee
        }

        $candidate->fill(Arr::except($data, ['stage']));

        if (isset($data['stage'])) {
            $candidate->stage = $data['stage'];
        }

        $candidate->save();

        return $candidate;
    }

    /**
     * @param  array{employee_number: string, branch_id: int, hired_on: string, basic_salary?: ?string, probation_days?: ?int, national_id?: ?string}  $data
     */
    public function hire(Candidate $candidate, array $data, ?Authenticatable $actor = null): Employee
    {
        if ($candidate->stage === 'hired') {
            throw ValidationException::withMessages(['stage' => __('hr.recruitment.errors.hired')]);
        }

        return DB::transaction(function () use ($candidate, $data, $actor): Employee {
            $opening = $candidate->opening;
            $employee = $this->saveEmployee->handle(new Employee, [
                'company_id' => $opening->company_id, 'branch_id' => $data['branch_id'], 'job_id' => $opening->job_id,
                'employee_number' => $data['employee_number'], 'name_ar' => $candidate->name, 'name_en' => $candidate->name,
                'phone' => $candidate->phone, 'email' => $candidate->email, 'nationality' => $candidate->nationality,
                'national_id' => $data['national_id'] ?? null, 'hired_on' => $data['hired_on'], 'status' => 'active',
            ], $actor);

            $salary = $data['basic_salary'] ?? $candidate->offer_salary;

            if ($salary !== null) {
                $this->saveContract->handle($employee, new Contract, [
                    'type' => 'open_ended', 'status' => 'active', 'starts_on' => $data['hired_on'], 'basic_salary' => (string) $salary,
                    'probation_days' => $data['probation_days'] ?? 90,
                ], [], $actor);
            }

            $this->onboard($employee, CarbonImmutable::parse($data['hired_on']), $actor);
            $candidate->forceFill(['stage' => 'hired', 'employee_id' => $employee->id])->save();
            $this->audit->record($candidate, 'hired', ['employee_id' => $employee->id], $actor);

            if ($opening->candidates()->where('stage', 'hired')->count() >= $opening->positions) {
                $opening->forceFill(['status' => 'closed'])->save();
            }

            return $employee;
        });
    }

    /** The new hire's first-week tasks, from the account's onboarding list. */
    private function onboard(Employee $employee, CarbonImmutable $startsOn, ?Authenticatable $actor): void
    {
        foreach (array_values((array) $this->settings->get('onboarding_tasks')) as $index => $key) {
            $this->tasks->save(new Task, [
                'title' => __("hr.recruitment.onboarding.{$key}"),
                'priority' => 'normal',
                'needs_photo' => false,
                'due_at' => $startsOn->addDays(min(6, $index + 1))->setTime(17, 0)->toDateTimeString(),
            ], $employee, null, $actor);
        }
    }
}
