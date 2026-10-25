<?php

namespace App\Actions\Requests;

use App\Enums\Permission;
use App\Enums\RequestType;
use App\Models\ClientRequest;
use App\Models\User;
use App\Notifications\NewRequestReceived;
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

        Notification::send(User::teamHolding(Permission::RequestsManage), new NewRequestReceived($request));

        return $request;
    }
}
