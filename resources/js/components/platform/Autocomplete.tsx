import { useMemo, useState, type KeyboardEvent, type ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { useLocale } from '@/lib/i18n';
import { matches } from '@/lib/search';
import { cn } from '@/lib/utils';

export interface Suggestion {
  value: string;
  label: string;
  hint?: string | null;
}

interface Props {
  value: string;
  onChange: (text: string) => void;
  /** Enter on the typed text or on a highlighted suggestion (gets the suggestion's value). */
  onSubmit: (value: string) => void;
  suggestions: Suggestion[];
  placeholder?: string;
  icon?: ReactNode;
  className?: string;
  /** At most this many suggestions are listed (default 8). */
  limit?: number;
}

/**
 * Baydaq text field with suggestions, for scan-or-type boxes: a barcode scanner types and presses
 * Enter, a person types part of a name and picks from the list. Replaces the browser's datalist.
 */
export default function Autocomplete({ value, onChange, onSubmit, suggestions, placeholder, icon, className, limit = 8 }: Props) {
  const { dir } = useLocale();
  const [focused, setFocused] = useState(false);
  const [active, setActive] = useState(-1);
  const shown = useMemo(() => (value.trim() ? suggestions.filter((item) => matches(`${item.label} ${item.hint ?? ''} ${item.value}`, value)).slice(0, limit) : []), [suggestions, value, limit]);
  const open = focused && shown.length > 0;

  function submit(chosen: string) {
    onSubmit(chosen);
    setActive(-1);
  }

  function onKey(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((current) => Math.max(-1, Math.min(shown.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1))));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      submit(active >= 0 && shown[active] ? shown[active].value : value);
    } else if (event.key === 'Escape') {
      setFocused(false);
    }
  }

  return (
    <Popover open={open}>
      <PopoverAnchor asChild>
        <div className={cn('relative', className)}>
          {icon && <span className="pointer-events-none absolute start-2.5 top-1/2 -translate-y-1/2 text-muted-foreground [&_svg]:size-4">{icon}</span>}
          <Input
            value={value} placeholder={placeholder} aria-label={placeholder} role="combobox" aria-expanded={open} aria-autocomplete="list" className={cn(icon && 'ps-8')}
            onChange={(event) => { onChange(event.target.value); setActive(-1); }} onKeyDown={onKey}
            onFocus={() => setFocused(true)} onBlur={() => setTimeout(() => setFocused(false), 120)}
          />
        </div>
      </PopoverAnchor>
      <PopoverContent dir={dir} className="w-(--radix-popover-trigger-width) p-1" onOpenAutoFocus={(event) => event.preventDefault()}>
        <div role="listbox">
          {shown.map((item, index) => (
            <button
              key={item.value} type="button" role="option" aria-selected={index === active} onMouseEnter={() => setActive(index)}
              onMouseDown={(event) => event.preventDefault()} onClick={() => submit(item.value)}
              className={cn('flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-start text-sm outline-none', index === active && 'bg-muted')}
            >
              <span className="truncate">{item.label}</span>
              {item.hint && <span dir="ltr" className="shrink-0 font-mono text-xs text-muted-foreground">{item.hint}</span>}
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}
