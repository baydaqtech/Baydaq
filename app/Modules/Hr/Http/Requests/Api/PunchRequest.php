<?php

namespace App\Modules\Hr\Http\Requests\Api;

use App\Modules\Hr\Enums\PunchType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PunchRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PunchType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'mocked' => ['nullable', 'boolean'],
            'wifi_bssid' => ['nullable', 'string', 'max:40'],
            'device_id' => ['required', 'string', 'max:120'],
            'punched_at' => ['nullable', 'date'],
        ];
    }
}
