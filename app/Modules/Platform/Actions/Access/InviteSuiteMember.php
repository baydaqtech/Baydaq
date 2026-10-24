<?php

namespace App\Modules\Platform\Actions\Access;

use App\Actions\Users\SendAccountInvite;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteSuiteMember
{
    public function __construct(private SendAccountInvite $sendAccountInvite) {}

    /**
     * Create a login for a person of the client (an employee or manager, not a client account)
     * and email them the set-password link. Roles are granted separately.
     *
     * @param  array{name: string, email: string, locale: string}  $details
     */
    public function handle(Client $client, array $details): User
    {
        $user = DB::transaction(function () use ($client, $details): User {
            $user = new User([...$details, 'password' => Str::password(32)]);
            $user->forceFill(['client_id' => $client->id, 'must_change_password' => true])->save();

            return $user;
        });

        $this->sendAccountInvite->handle($user);

        return $user;
    }
}
