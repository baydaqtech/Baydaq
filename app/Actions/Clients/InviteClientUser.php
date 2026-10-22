<?php

namespace App\Actions\Clients;

use App\Actions\Users\SendAccountInvite;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteClientUser
{
    public function __construct(private SendAccountInvite $sendAccountInvite) {}

    /**
     * Create a portal account for the client and email the "your account is ready" link.
     *
     * @param  array{name: string, email: string, locale: string}  $details
     */
    public function handle(Client $client, array $details): User
    {
        $user = DB::transaction(function () use ($client, $details): User {
            $user = new User([...$details, 'password' => Str::password(32)]);
            $user->forceFill(['client_id' => $client->id, 'must_change_password' => true])->save();
            $user->assignRole(Role::Client);

            return $user;
        });

        $this->sendAccountInvite->handle($user);

        return $user;
    }
}
