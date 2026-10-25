<?php

namespace App\Modules\Platform\Ports;

use Illuminate\Notifications\Notification;

/**
 * Logins of the current tenant. HR links an employee to a login through here; the kernel
 * owns users and invites.
 */
interface Accounts
{
    /** Create a login for a person of the tenant and email them the set-password link. Returns the user id. */
    public function invite(string $name, string $email, string $locale): int;

    /** The user id of an existing login of the tenant with this email, if any. */
    public function findByEmail(string $email): ?int;

    /** @return array{id: int, name: string, email: string, pending: bool}|null */
    public function summary(int $userId): ?array;

    /** Send a notification to a login of the tenant. */
    public function notify(int $userId, Notification $notification): void;

    /** Sign a login out of every app (revoke its API tokens), e.g. when the person leaves. */
    public function signOutEverywhere(int $userId): void;
}
