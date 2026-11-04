import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

export type Filters = Record<string, string | number | null | undefined>;

/**
 * Keeps index filters in the query string: changing one reloads the list
 * (search is debounced), keeping the page state and history tidy.
 */
export function useFilters(initial: Filters): [Filters, (key: string, value: string | number | null, options?: { debounce?: boolean }) => void] {
  const [filters, setFilters] = useState<Filters>(initial);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => () => { if (timer.current) clearTimeout(timer.current); }, []);

  function visit(next: Filters) {
    const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)) as Record<string, string | number>;
    router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true, replace: true });
  }

  function set(key: string, value: string | number | null, { debounce = false }: { debounce?: boolean } = {}) {
    const next = { ...filters, [key]: value };
    setFilters(next);
    if (timer.current) clearTimeout(timer.current);

    if (debounce) {
      timer.current = setTimeout(() => visit(next), 300);
    } else {
      visit(next);
    }
  }

  return [filters, set];
}
