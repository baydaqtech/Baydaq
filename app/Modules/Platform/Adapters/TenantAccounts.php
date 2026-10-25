<?php

namespace App\Modules\Platform\Adapters;

use App\Models\User;
use App\Modules\Platform\Actions\Access\InviteSuiteMember;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Notifications\Notification;

class TenantAccounts implements Accounts
{
    public function __construct(private CurrentTenant $tenant, private InviteSuiteMember $inviteSuiteMember) {}

    public function invite(string $name, string $email, string $locale): int
    {
        return $this->inviteSuiteMember->handle($this->tenant->client(), ['name' => $name, 'email' => $email, 'locale' => $locale])->id;
    }

    public function findByEmail(string $email): ?int
    {
        return User::query()->where('client_id', $this->tenant->id())->where('email', mb_strtolower(trim($email)))->value('id');
    }

    public function summary(int $userId): ?array
    {
        $user = User::query()->where('client_id', $this->tenant->id())->find($userId);

        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'pending' => (bool) $user->must_change_password] : null;
    }

    public function notify(int $userId, Notification $notification): void
    {
        User::query()->where('client_id', $this->tenant->id())->find($userId)?->notify($notification);
    }

    public function signOutEverywhere(int $userId): void
    {
        User::query()->where('client_id', $this->tenant->id())->find($userId)?->tokens()->delete();
    }
}
