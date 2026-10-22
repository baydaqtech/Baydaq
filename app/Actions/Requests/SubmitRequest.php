<?php

namespace App\Actions\Requests;

use App\Enums\Permission;
use App\Enums\RequestType;
use App\Enums\Role;
use App\Models\ClientRequest;
use App\Models\User;
use App\Notifications\NewRequestReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class SubmitRequest
{
    /**
     * Save a request from the public forms and tell the team.
     *
     * @param  array{name: string, company?: string|null, email: string, phone?: string|null, message: string, services?: list<string>, locale: string, ip: string|null}  $data
     */
    public function handle(RequestType $type, array $data): ClientRequest
    {
        $request = ClientRequest::create([...$data, 'type' => $type]);

        Notification::send($this->teamWhoHandlesRequests(), new NewRequestReceived($request));

        return $request;
    }

    /**
     * System admins plus anyone with the requests.manage permission (directly or through a role).
     *
     * @return Collection<int, User>
     */
    private function teamWhoHandlesRequests(): Collection
    {
        $canManage = fn (Builder $query) => $query->where('name', Permission::RequestsManage->value);

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::SystemAdmin->value))
            ->orWhereHas('permissions', $canManage)
            ->orWhereHas('roles.permissions', $canManage)
            ->get();
    }
}
