import { usePage } from '@inertiajs/react';

/** can('invoices.manage') for the signed-in team member (the system admin has every permission). */
export function useCan() {
  const permissions = usePage().props.auth.user?.permissions ?? [];

  return (...names) => names.some((name) => permissions.includes(name));
}
