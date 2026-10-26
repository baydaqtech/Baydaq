<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sensitive fields appear only when the controller passes the matching ability flags.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @param  array{view_iban?: bool, view_national_id?: bool, view_salaries?: bool, update?: bool, link_login?: bool}  $can
     */
    /** @var array<string, bool> */
    private array $can;

    public function __construct($resource, mixed $can = [])
    {
        parent::__construct($resource);
        // ::collection() passes the collection key here; only an explicit ability map counts.
        $this->can = is_array($can) ? $can : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'employee_number' => $this->employee_number,
            'name' => $this->localized('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'email' => $this->email,
            'phone' => $this->phone,
            'section_id' => $this->section_id,
            'section' => $this->whenLoaded('section', fn () => $this->section?->localized('name')),
            'job_id' => $this->job_id,
            'job' => $this->whenLoaded('job', fn () => $this->job?->localized('name')),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager?->localized('name')),
            'id_type' => $this->id_type?->value,
            'national_id' => ($this->can['view_national_id'] ?? false) ? $this->national_id : null,
            'nationality' => $this->nationality,
            'gender' => $this->gender?->value,
            'birth_date' => $this->birth_date?->toDateString(),
            'hired_on' => $this->hired_on?->toDateString(),
            'gosi_registered_on' => $this->gosi_registered_on?->toDateString(),
            'terminated_on' => $this->terminated_on?->toDateString(),
            'status' => $this->status->value,
            'iban' => ($this->can['view_iban'] ?? false) ? $this->iban : $this->maskedIban(),
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'can' => $this->can,
        ];
    }
}
