<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ClientDocument;
use App\Models\User;

class ClientDocumentPolicy
{
    /**
     * The team with client access, or a client account of the client the document was shared with.
     * Suite users (employees) share the client_id but are not client accounts.
     */
    public function view(User $user, ClientDocument $document): bool
    {
        if ($user->isAdmin()) {
            return $user->hasAdminPermission(Permission::ClientsView, Permission::ClientsManage);
        }

        return $user->hasRole(Role::Client) && $user->client_id !== null && $user->client_id === $document->client_id;
    }
}
