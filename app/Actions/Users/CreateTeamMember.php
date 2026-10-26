<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeamMember
{
    public function __construct(private SendAccountInvite $sendAccountInvite) {}

    /**
     * Add a team member with the chosen permissions and email their invite.
     *
     * @param  array{name: string, email: string, locale: string}  $details
     * @param  list<string>  $permissions
     */
    public function handle(array $details, array $permissions): User
    {
        $user = DB::transaction(function () use ($details, $permissions): User {
            $user = new User([...$details, 'password' => Str::password(32)]);
            $user->forceFill(['must_change_password' => true])->save();
            $user->assignRole(Role::Admin);
            $user->syncPermissions($permissions);

            return $user;
        });

        $this->sendAccountInvite->handle($user);

        return $user;
    }
}
