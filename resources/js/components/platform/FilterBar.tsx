import { Search } from 'lucide-react';
import SelectInput from '@/components/platform/SelectInput';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import type { Filters } from '@/lib/useFilters';
import type { Option } from '@/types/resources';

interface Props {
  filters: Filters;
  setFilter: (key: string, value: string | number | null, options?: { debounce?: boolean }) => void;
  selects?: { key: string; label: string; options: Option[] }[];
  search?: boolean;
}

/**
 * Search box plus select filters for an index page.
 * `selects`: [{ key, label, options }] — each gets an "All" choice.
 */
export default function FilterBar({ filters, setFilter, selects = [], search = true }: Props) {
  const t = useT();

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      {search && (
        <div className="relative w-full sm:w-64">
          <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={String(filters.search ?? '')}
            onChange={(event) => setFilter('search', event.target.value, { debounce: true })}
            placeholder={t('common.actions.search')}
            className="ps-8"
          />
        </div>
      )}
      {selects.map((select) => (
        <div key={select.key} className="w-full sm:w-44">
          <SelectInput
            value={filters[select.key] ?? ''}
            onChange={(value) => setFilter(select.key, value)}
            options={select.options}
            emptyLabel={`${select.label}: ${t('common.actions.all')}`}
          />
        </div>
      ))}
    </div>
  );
}
