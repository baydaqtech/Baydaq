<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\ContractAllowance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allowances = $this->whenLoaded('allowances', fn () => $this->allowances->map(fn (ContractAllowance $line): array => [
            'id' => $line->id,
            'allowance_type_id' => $line->allowance_type_id,
            'name' => $line->type?->localized('name'),
            'calculation' => $line->calculation->value,
            'value' => (string) $line->value,
            'amount' => (string) $line->amount,
        ])->all());

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'probation_days' => $this->probation_days,
            'basic_salary' => (string) $this->basic_salary,
            'qiwa_authenticated' => (bool) $this->qiwa_authenticated,
            'total' => $this->relationLoaded('allowances') ? (string) $this->allowances->sum(fn (ContractAllowance $line) => (float) $line->amount) + (float) $this->basic_salary : null,
            'notes' => $this->notes,
            'allowances' => $allowances,
        ];
    }
}
