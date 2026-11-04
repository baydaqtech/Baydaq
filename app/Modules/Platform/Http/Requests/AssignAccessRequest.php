<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAccessRequest extends FormRequest
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

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('client_id', $tenantId)],
            'access_role_id' => ['required', Rule::exists('platform__access_roles', 'id')->where('client_id', $tenantId)],
            'level' => ['required', Rule::enum(ScopeLevel::class)],
            'node_id' => ['nullable', 'integer', Rule::requiredIf(fn (): bool => $this->input('level') !== ScopeLevel::Client->value)],
        ];
    }
}
