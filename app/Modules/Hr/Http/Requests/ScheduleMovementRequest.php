<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Enums\MovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleMovementRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'to_branch_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', Rule::requiredIf(fn () => $this->input('type') === MovementType::Secondment->value)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
