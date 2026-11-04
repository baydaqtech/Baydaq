<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOrganizationRequest extends FormRequest
{
    /**
     * Access is checked by the route's access: middleware and the controller.
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
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'alpha_dash:ascii', 'max:60',
                Rule::unique('platform__organizations', 'slug')
                    ->where('client_id', app(CurrentTenant::class)->id())
                    ->ignore($this->route('organization')),
            ],
        ];
    }
}
