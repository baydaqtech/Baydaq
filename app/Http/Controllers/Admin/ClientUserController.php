<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Clients\InviteClientUser;
use App\Actions\Users\SendAccountInvite;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteUserRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ClientUserController extends Controller
{
    use RendersAdminPages;

    /**
     * Add a portal account to the client and send the invite.
     */
    public function store(InviteUserRequest $request, Client $client, InviteClientUser $inviteClientUser): RedirectResponse
    {
        $inviteClientUser->handle($client, $request->validated());
        $this->toast(__('admin.clients.users.invited'));

        return back();
    }

    /**
     * Send a fresh set-password link; the earlier one stops working.
     */
    public function resendInvite(Client $client, User $user, SendAccountInvite $sendAccountInvite): RedirectResponse
    {
        abort_unless($user->client_id === $client->id, 404);

        $sendAccountInvite->handle($user);
        $this->toast(__('admin.clients.users.resent'));

        return back();
    }
}
