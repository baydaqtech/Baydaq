<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\EmployeeMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Branch names are filled by the controller from the OrgDirectory (HR never joins platform tables).
 *
 * @mixin EmployeeMovement
 */
class MovementResource extends JsonResource
{
    /**
     * @param  array<int, string>  $branchNames  branch id → name
     */
    public function __construct($resource, private array $branchNames = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee->localized('name')),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'from_branch' => $this->branchNames[$this->from_branch_id] ?? (string) $this->from_branch_id,
            'to_branch' => $this->branchNames[$this->to_branch_id] ?? (string) $this->to_branch_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'reason' => $this->reason,
            'cancellable' => in_array($this->status->value, ['scheduled', 'active'], true) && ($this->status->value === 'scheduled' || $this->type->value === 'secondment'),
        ];
    }
}
