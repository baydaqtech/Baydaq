<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\Gender;
use App\Modules\Hr\Enums\IdType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();
        $employee = $this->route('employee');
        $companyId = $employee instanceof Employee ? $employee->company_id : $this->integer('company_id');

        return [
            'company_id' => [Rule::requiredIf(! $employee), 'integer'],
            'branch_id' => [Rule::requiredIf(! $employee), 'integer'],
            'employee_number' => [
                'required', 'string', 'max:30',
                Rule::unique('hr__employees', 'employee_number')->where('company_id', $companyId)->ignore($employee),
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'section_id' => ['nullable', Rule::exists('hr__sections', 'id')->where('client_id', $tenantId)],
            'job_id' => ['nullable', Rule::exists('hr__jobs', 'id')->where('client_id', $tenantId)],
            'manager_id' => ['nullable', Rule::exists('hr__employees', 'id')->where('client_id', $tenantId), Rule::notIn([$employee?->id])],
            'id_type' => ['nullable', Rule::enum(IdType::class)],
            'national_id' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'hired_on' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9A-Z]{13,32}$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iban' => $this->filled('iban') ? strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban'))) : null,
            'nationality' => $this->filled('nationality') ? strtoupper((string) $this->input('nationality')) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }
}
