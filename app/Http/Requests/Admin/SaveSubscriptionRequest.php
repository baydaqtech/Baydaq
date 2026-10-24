<?php

namespace App\Http\Requests\Admin;

use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\SubscriptionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubscriptionRequest extends FormRequest
{
    /**
     * Access is checked by the route's permission:clients.manage middleware.
     */
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
            'module' => ['required', Rule::enum(Module::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionData(): array
    {
        $data = $this->validated();
        $data['billing_unit'] = Module::from($data['module'])->billingUnit();

        return $data;
    }
}
