import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Keeps index filters in the query string: changing one reloads the list
 * (search is debounced), keeping the page state and history tidy.
 */
export function useFilters(initial) {
  const [filters, setFilters] = useState(initial);
  const timer = useRef(null);

  useEffect(() => () => clearTimeout(timer.current), []);

  function visit(next) {
    const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true, replace: true });
  }

  function set(key, value, { debounce = false } = {}) {
    const next = { ...filters, [key]: value };
    setFilters(next);
    clearTimeout(timer.current);

    if (debounce) {
      timer.current = setTimeout(() => visit(next), 300);
    } else {
      visit(next);
    }
  }

  return [filters, set];
}
