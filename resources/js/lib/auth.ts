import { usePage } from '@inertiajs/react';

/** can('invoices.manage') for the signed-in team member (the system admin has every permission). */
export function useCan(): (...names: string[]) => boolean {
  const props = usePage().props as unknown as { auth: { user: { permissions: string[] } | null } };
  const permissions = props.auth.user?.permissions ?? [];

  return (...names) => names.some((name) => permissions.includes(name));
}
