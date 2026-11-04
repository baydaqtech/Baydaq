import { useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Check, ChevronsUpDown, Search } from 'lucide-react';
import PickerTrigger from '@/components/platform/PickerTrigger';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useLocale, useT } from '@/lib/i18n';
import { matches } from '@/lib/search';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface Props {
  id?: string;
  value: string | number | null | undefined;
  onChange: (value: string) => void;
  options: Option[];
  /** A first choice meaning none / all, sent as "". */
  emptyLabel?: string;
  placeholder?: string;
  invalid?: boolean;
  disabled?: boolean;
  className?: string;
}

/**
 * Baydaq searchable select: type to filter (Arabic-aware), arrows to move, Enter to choose.
 * SelectInput switches to it for long lists, so most screens get it without asking.
 */
export default function Combobox({ id, value, onChange, options, emptyLabel, placeholder, invalid, disabled, className }: Props) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  const list = useRef<HTMLDivElement>(null);
  const choices = useMemo(() => [...(emptyLabel ? [{ value: '', label: emptyLabel }] : []), ...options], [emptyLabel, options]);
  const shown = useMemo(() => choices.filter((option) => matches(option.label, query)), [choices, query]);
  const current = choices.find((option) => String(option.value) === String(value ?? ''));

  function choose(option: Option) {
    onChange(String(option.value));
    setOpen(false);
    setQuery('');
  }

  function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = Math.max(0, Math.min(shown.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1)));
      setActive(next);
      list.current?.children[next]?.scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter' && shown[active]) {
      event.preventDefault();
      choose(shown[active]);
    }
  }

  return (
    <Popover open={open} onOpenChange={(next) => { setOpen(next); setActive(Math.max(0, shown.findIndex((option) => option === current))); }}>
      <PopoverTrigger asChild>
        <PickerTrigger
          id={id} disabled={disabled} invalid={invalid} className={cn('[&>svg:last-child]:ms-auto', className)} role="combobox" aria-expanded={open}
          icon={<ChevronsUpDown className="order-last" />} placeholder={placeholder ?? t('common.actions.choose')} display={current?.label}
        />
      </PopoverTrigger>
      <PopoverContent dir={dir} className="w-(--radix-popover-trigger-width) min-w-56 p-1">
        <div className="flex items-center gap-2 border-b px-2 pb-1">
          <Search className="size-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus value={query} onKeyDown={onKey} placeholder={t('common.ui.search_options')} aria-label={t('common.ui.search_options')}
            onChange={(event) => { setQuery(event.target.value); setActive(0); }}
            className="h-8 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
        </div>
        <div ref={list} role="listbox" className="mt-1 max-h-64 overflow-y-auto">
          {shown.length === 0 && <p className="px-2 py-6 text-center text-sm text-muted-foreground">{t('common.ui.no_matches')}</p>}
          {shown.map((option, index) => {
            const selected = String(option.value) === String(value ?? '');

            return (
              <button
                key={String(option.value)} type="button" role="option" aria-selected={selected} onMouseEnter={() => setActive(index)} onClick={() => choose(option)}
                className={cn('flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm outline-none', index === active && 'bg-muted')}
              >
                <Check className={cn('size-4 shrink-0', selected ? 'opacity-100' : 'opacity-0')} />
                <span className="truncate">{option.label}</span>
              </button>
            );
          })}
        </div>
      </PopoverContent>
    </Popover>
  );
}
