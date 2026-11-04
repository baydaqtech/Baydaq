<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A team member: InviteUserRequest's fields (email only when creating) plus the permission checklist.
 */
class SaveTeamMemberRequest extends InviteUserRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            ...parent::rules(),
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ];

        if ($this->route('member')) {
            unset($rules['email']);
        }

        return $rules;
    }
}
