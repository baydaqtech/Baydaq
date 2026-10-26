<?php

namespace App\Actions\Requests;

use App\Actions\Users\SendAccountInvite;
use App\Enums\ClientStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApproveRequest
{
    public function __construct(private SendAccountInvite $sendAccountInvite) {}

    /**
     * Turn a request into a client with a portal account and invite them.
     * If the email already has a client account, the request is linked to that client instead.
     *
     * @return bool whether a new account was created (and invited)
     */
    public function handle(ClientRequest $request, User $approvedBy): bool
    {
        $existing = User::query()->where('email', mb_strtolower($request->email))->first();

        if ($existing?->isAdmin()) {
            throw ValidationException::withMessages(['email' => __('admin.requests.email_is_team')]);
        }

        $newUser = DB::transaction(function () use ($request, $approvedBy, $existing): ?User {
            if ($existing?->client_id) {
                $this->markApproved($request, $approvedBy, $existing->client_id);

                return null;
            }

            $client = Client::create([
                'name_ar' => $request->company ?: $request->name,
                'name_en' => $request->company ?: $request->name,
                'contact_name' => $request->name,
                'contact_email' => mb_strtolower($request->email),
                'contact_phone' => $request->phone,
                'status' => ClientStatus::Active,
            ]);

            $user = $existing ?? new User(['password' => Str::password(32)]);
            $user->fill(['name' => $request->name, 'email' => $request->email, 'locale' => $request->locale]);
            $user->forceFill(['client_id' => $client->id, 'must_change_password' => true])->save();
            $user->assignRole(Role::Client);

            $this->markApproved($request, $approvedBy, $client->id);

            return $user;
        });

        if ($newUser) {
            $this->sendAccountInvite->handle($newUser);
        }

        return $newUser !== null;
    }

    private function markApproved(ClientRequest $request, User $approvedBy, int $clientId): void
    {
        $request->forceFill([
            'status' => RequestStatus::Approved,
            'handled_by' => $approvedBy->id,
            'client_id' => $clientId,
        ])->save();
    }
}
