import { useEffect, useRef, useState } from 'react';
import { Clock } from 'lucide-react';
import PickerTrigger from '@/components/platform/PickerTrigger';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { pad } from '@/lib/dates';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Props {
  id?: string;
  /** "HH:MM" (24-hour), or empty for none. */
  value?: string | null;
  onChange: (value: string) => void;
  /** Minutes between choices (default 5). */
  step?: number;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  invalid?: boolean;
  'aria-label'?: string;
}

function formatTime(value: string, locale: string): string {
  const [hours, minutes] = value.split(':').map(Number);

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'UTC' })
    .format(new Date(Date.UTC(2026, 0, 1, hours, minutes)));
}

/** Baydaq time field: hour and minute columns in a popover instead of the browser's time picker. */
export default function TimeField({ id, value, onChange, step = 5, placeholder, className, disabled, invalid, 'aria-label': ariaLabel }: Props) {
  const t = useT();
  const { locale, dir } = useLocale();
  const [open, setOpen] = useState(false);
  const [hours, minutes] = value && /^\d{2}:\d{2}/.test(value) ? value.slice(0, 5).split(':').map(Number) : [null, null];
  const list = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (open) {
      requestAnimationFrame(() => list.current?.querySelectorAll<HTMLElement>('[data-selected="true"]').forEach((item) => item.scrollIntoView({ block: 'center' })));
    }
  }, [open]);

  const column = (label: string, items: number[], current: number | null, choose: (item: number) => void) => (
    <div className="flex flex-col">
      <span className="pb-1 text-center text-xs font-medium text-muted-foreground">{label}</span>
      <div role="listbox" aria-label={label} className="flex h-48 w-14 flex-col gap-0.5 overflow-y-auto pe-1">
        {items.map((item) => (
          <button
            key={item} type="button" role="option" aria-selected={item === current} data-selected={item === current} onClick={() => choose(item)}
            className={cn('rounded-md py-1 text-sm tabular-nums outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50', item === current && 'bg-primary text-primary-foreground hover:bg-primary/90')}
          >
            {new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', { minimumIntegerDigits: 2 }).format(item)}
          </button>
        ))}
      </div>
    </div>
  );

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <PickerTrigger
          id={id} className={className} disabled={disabled} invalid={invalid} aria-label={ariaLabel}
          icon={<Clock />} placeholder={placeholder ?? t('common.ui.choose_time')} display={value ? formatTime(value, locale) : null}
        />
      </PopoverTrigger>
      <PopoverContent dir={dir} className="w-auto">
        <div ref={list} dir="ltr" className="flex gap-2">
          {column(t('common.ui.hours'), Array.from({ length: 24 }, (_, index) => index), hours, (hour) => onChange(`${pad(hour)}:${pad(minutes ?? 0)}`))}
          {column(t('common.ui.minutes'), Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step), minutes, (minute) => { onChange(`${pad(hours ?? 9)}:${pad(minute)}`); setOpen(false); })}
        </div>
      </PopoverContent>
    </Popover>
  );
}
