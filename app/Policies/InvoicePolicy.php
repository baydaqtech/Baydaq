<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Statuses a client can see; drafts and voided invoices stay internal.
     */
    private const VISIBLE_TO_CLIENTS = [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission(Permission::InvoicesView, Permission::InvoicesManage);
    }

    /**
     * The team with invoice access, or the client the invoice was issued to.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return $this->viewAny($user);
        }

        return $user->client_id !== null
            && $user->client_id === $invoice->client_id
            && in_array($invoice->status, self::VISIBLE_TO_CLIENTS, true);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAdminPermission(Permission::InvoicesManage);
    }

    /**
     * Only drafts are edited; once sent, an invoice is changed by marking it paid or void.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft && $user->hasAdminPermission(Permission::InvoicesManage);
    }

    /**
     * Mark sent, paid or void.
     */
    public function changeStatus(User $user, Invoice $invoice): bool
    {
        return $user->hasAdminPermission(Permission::InvoicesManage);
    }
}
