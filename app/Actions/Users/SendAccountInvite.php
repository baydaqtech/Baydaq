<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\AccountReady;
use Illuminate\Support\Facades\Password;

class SendAccountInvite
{
    /**
     * Email a fresh set-password link. Any earlier invite link for this user stops working.
     */
    public function handle(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        $user->notify(new AccountReady($token));
    }
}
