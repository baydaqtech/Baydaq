import { Search } from 'lucide-react';
import SelectInput from '@/components/platform/SelectInput';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

/**
 * Search box plus select filters for an index page.
 * `selects`: [{ key, label, options }] — each gets an "All" choice.
 */
export default function FilterBar({ filters, setFilter, selects = [], search = true }) {
  const t = useT();

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      {search && (
        <div className="relative w-full sm:w-64">
          <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={filters.search ?? ''}
            onChange={(event) => setFilter('search', event.target.value, { debounce: true })}
            placeholder={t('admin.common.search')}
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
            emptyLabel={`${select.label}: ${t('admin.common.all')}`}
          />
        </div>
      ))}
    </div>
  );
}
