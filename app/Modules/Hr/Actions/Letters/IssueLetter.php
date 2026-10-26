<?php

namespace App\Modules\Hr\Actions\Letters;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\LetterKind;
use App\Modules\Hr\Models\ContractAllowance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Letter;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issue a salary or experience letter (H12). What the letter states is frozen in a snapshot so
 * the verification page shows exactly what was printed.
 */
class IssueLetter
{
    public function __construct(private OrgDirectory $directory, private Audit $audit) {}

    public function handle(Employee $employee, LetterKind $kind, string $language, ?string $addressedTo, ?Authenticatable $actor = null): Letter
    {
        $employee->loadMissing(['job', 'activeContract.allowances']);
        $contract = $employee->activeContract;

        if ($kind === LetterKind::Salary && ($contract === null || $employee->status !== EmployeeStatus::Active)) {
            throw ValidationException::withMessages(['kind' => __('hr.letters.needs_contract')]);
        }

        $company = $this->directory->company($employee->company_id);
        $allowances = $contract?->allowances->reduce(fn (BigDecimal $sum, ContractAllowance $line) => $sum->plus((string) $line->amount), BigDecimal::zero()) ?? BigDecimal::zero();

        $snapshot = [
            'company' => ['name_ar' => $company?->nameAr, 'name_en' => $company?->nameEn, 'cr_number' => $company?->crNumber],
            'employee' => [
                'name_ar' => $employee->name_ar, 'name_en' => $employee->name_en, 'number' => $employee->employee_number,
                'nationality' => $employee->nationality, 'id_type' => $employee->id_type?->value,
                'id_last4' => $employee->national_id ? substr((string) $employee->national_id, -4) : null,
                'job_ar' => $employee->job?->name_ar, 'job_en' => $employee->job?->name_en,
                'hired_on' => $employee->hired_on?->toDateString(), 'left_on' => $employee->terminated_on?->toDateString(),
            ],
            'salary' => $kind === LetterKind::Salary ? [
                'basic' => (string) $contract->basic_salary,
                'allowances' => (string) $allowances->toScale(2),
                'total' => (string) BigDecimal::of((string) $contract->basic_salary)->plus($allowances)->toScale(2),
            ] : null,
        ];

        return DB::transaction(function () use ($employee, $kind, $language, $addressedTo, $actor, $snapshot): Letter {
            $count = Letter::query()->whereYear('issued_at', now()->year)->lockForUpdate()->count() + 1;

            $letter = (new Letter)->forceFill([
                'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'kind' => $kind, 'language' => $language,
                'addressed_to' => $addressedTo, 'reference' => 'HR-'.now()->format('Y').'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT),
                'token' => Str::random(40), 'snapshot' => $snapshot, 'issued_by' => $actor?->getAuthIdentifier(), 'issued_at' => now(),
            ]);
            $letter->save();
            $this->audit->record($letter, 'issued', ['kind' => $kind->value, 'employee_id' => $employee->id], $actor);

            return $letter;
        });
    }
}
