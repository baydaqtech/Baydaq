<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Actions\Messages\MessageBuilder;
use App\Modules\Hr\Adapters\EmployeeStaffDirectory;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\PlacementKind;
use App\Modules\Hr\Events\EmployeeHired;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Section;
use App\Modules\Hr\Ports\OffboardingClearance;
use App\Modules\Hr\Support\BlindIndex;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEmployee
{
    public function __construct(
        private OrgDirectory $directory,
        private EnsureSeatAvailable $ensureSeatAvailable,
        private Audit $audit,
        private CurrentTenant $tenant,
        private OffboardingClearance $clearance,
        private DualControl $dualControl,
    ) {}

    /**
     * Create or update an employee. A new employee is placed at a company and home branch (the
     * branch must belong to the company) and gets an open-ended home placement; the job must be
     * linked to the section and neither may be blocked for the company. Activating uses a seat.
     *
     * @param  array<string, mixed>  $data  validated fields plus company_id, branch_id and status
     */
    public function handle(Employee $employee, array $data, ?Authenticatable $actor = null): Employee
    {
        // Under the two-person rule a new IBAN waits for someone else's approval.
        if ($employee->exists && array_key_exists('iban', $data) && $data['iban'] !== $employee->iban && $this->dualControl->enabled()) {
            $this->dualControl->hold($employee, 'iban', ['iban' => $data['iban']], $this->sensitiveChanges($employee, ['iban' => $data['iban']]), null, $actor);
            unset($data['iban']);
        }

        $companyId = (int) ($data['company_id'] ?? $employee->company_id);
        $branchId = (int) ($data['branch_id'] ?? $employee->branch_id);
        $branch = $this->directory->branch($branchId);

        if ($branch === null || $branch->companyId !== $companyId) {
            throw ValidationException::withMessages(['branch_id' => __('hr.employees.errors.branch_company')]);
        }

        $this->ensureJobAllowed($data['section_id'] ?? null, $data['job_id'] ?? null, $companyId);
        $this->ensureUniqueNationalId($data['national_id'] ?? null, $employee);

        if ($employee->exists && ($data['status'] ?? null) === EmployeeStatus::Terminated->value && $employee->status !== EmployeeStatus::Terminated
            && ($blockers = $this->clearance->blockers($employee->id)) !== []) {
            throw ValidationException::withMessages(['status' => $blockers]);
        }

        return DB::transaction(function () use ($employee, $data, $companyId, $branchId, $actor): Employee {
            $isNew = ! $employee->exists;
            $status = EmployeeStatus::from($data['status'] ?? $employee->status->value);
            $wasSeated = ! $isNew && $employee->status->usesSeat();

            if ($status->usesSeat() && ! $wasSeated) {
                $this->ensureSeatAvailable->handle($employee->exists ? $employee : null);
            }

            $changes = $this->sensitiveChanges($employee, $data);

            $employee->fill(collect($data)->except(['company_id', 'branch_id', 'status'])->all());

            if ($isNew) {
                $employee->forceFill(['client_id' => $this->tenant->client()->id, 'company_id' => $companyId, 'branch_id' => $branchId]);
            }

            if ($status === EmployeeStatus::Terminated && $employee->terminated_on === null) {
                $employee->terminated_on = today();
            }

            // Set explicitly rather than only in the model hook: seeders run without model events.
            $employee->forceFill(['status' => $status->value, 'national_id_hash' => BlindIndex::of($employee->national_id)])->save();

            if ($isNew) {
                $employee->placements()->make()->forceFill([
                    'client_id' => $employee->client_id,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'kind' => PlacementKind::Home->value,
                    'starts_on' => $employee->hired_on ?? today(),
                ])->save();

                $this->audit->record($employee, 'created', null, $actor);
                EmployeeHired::dispatch(EmployeeStaffDirectory::summary($employee));
                MessageBuilder::afterCommit('hired', $employee, 'hired');
            } else {
                $this->audit->record($employee, 'updated', $changes ?: null, $actor);

                if ($employee->wasChanged('job_id') && $employee->job_id !== null) {
                    MessageBuilder::afterCommit('job_changed', $employee, 'job:'.$employee->job_id.':'.now()->format('Ymd'));
                }
            }

            return $employee;
        });
    }

    private function ensureJobAllowed(?int $sectionId, ?int $jobId, int $companyId): void
    {
        if ($sectionId === null && $jobId === null) {
            return;
        }

        $section = $sectionId ? Section::query()->find($sectionId) : null;
        $job = $jobId ? Job::query()->find($jobId) : null;

        if ($jobId && $sectionId && ! $job?->sections()->whereKey($sectionId)->exists()) {
            throw ValidationException::withMessages(['job_id' => __('hr.employees.errors.job_not_in_section')]);
        }

        if ($section && in_array($companyId, $section->blockedCompanyIds(), true)) {
            throw ValidationException::withMessages(['section_id' => __('hr.employees.errors.section_blocked')]);
        }

        if ($job && in_array($companyId, $job->blockedCompanyIds(), true)) {
            throw ValidationException::withMessages(['job_id' => __('hr.employees.errors.job_blocked')]);
        }
    }

    private function ensureUniqueNationalId(?string $nationalId, Employee $employee): void
    {
        $hash = BlindIndex::of($nationalId);

        if ($hash && Employee::query()->where('national_id_hash', $hash)->whereKeyNot($employee->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['national_id' => __('hr.employees.errors.national_id_taken')]);
        }
    }

    /**
     * Masked before/after values for the audit row; the raw numbers never leave the encrypted columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: ?string, to: ?string}>
     */
    private function sensitiveChanges(Employee $employee, array $data): array
    {
        $changes = [];

        if (array_key_exists('iban', $data) && $employee->exists && $data['iban'] !== $employee->iban) {
            $changes['iban'] = ['from' => $employee->maskedIban(), 'to' => $data['iban'] ? '****'.substr($data['iban'], -4) : null];
        }

        if (array_key_exists('national_id', $data) && $employee->exists && $data['national_id'] !== $employee->national_id) {
            $changes['national_id'] = ['from' => $employee->national_id ? '****'.substr($employee->national_id, -3) : null, 'to' => $data['national_id'] ? '****'.substr($data['national_id'], -3) : null];
        }

        return $changes;
    }
}
