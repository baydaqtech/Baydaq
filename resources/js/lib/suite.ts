import { usePage } from '@inertiajs/react';
import type { SuitePageProps, SuiteProp } from '@/types/suite';

/** The current scope, the reachable tree and the permission keys the user holds in it. */
export function useSuite(): SuiteProp {
  return usePage<SuitePageProps>().props.suite;
}

/** can('hr.employees.create') for suite pages; owners hold every key. */
export function useAccess() {
  const { can: keys, is_owner } = useSuite();

  return {
    can: (...wanted: string[]) => is_owner || wanted.some((key) => keys.includes(key)),
    isOwner: is_owner,
  };
}

/** A suite URL inside the current scope: suiteUrl('/hr/employees') → /suite/c-acme/hr/employees?branches=3,4 */
export function useSuiteUrl() {
  const { scope } = useSuite();

  return (path: string, segment: string = scope.segment, branches: number[] = scope.branches) => {
    const query = branches.length ? `?branches=${branches.join(',')}` : '';
    return `/suite/${segment}${path.startsWith('/') ? path : `/${path}`}${query}`;
  };
}
