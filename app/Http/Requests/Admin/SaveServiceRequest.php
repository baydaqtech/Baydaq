<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceRequest extends FormRequest
{
    /**
     * Access is checked by the route's can:services.manage middleware.
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
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('services', 'key')->ignore($this->route('service'))],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'docs_url' => ['nullable', 'url', 'max:2048'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pricing_model' => ['required', Rule::enum(PricingModel::class)],
            'unit' => ['nullable', 'string', 'max:40'],
        ];
    }
}
