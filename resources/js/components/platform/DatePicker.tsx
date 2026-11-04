import { useState } from 'react';
import { CalendarDays } from 'lucide-react';
import Calendar from '@/components/platform/Calendar';
import PickerTrigger from '@/components/platform/PickerTrigger';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDay, formatHijri, parseIsoDate, todayIso, withinRange } from '@/lib/dates';
import { useLocale, useT } from '@/lib/i18n';

export interface DatePickerProps {
  id?: string;
  /** "YYYY-MM-DD", or empty for none. */
  value?: string | null;
  onChange: (value: string) => void;
  min?: string | null;
  max?: string | null;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  invalid?: boolean;
  /** Offer "Clear" (on by default). */
  clearable?: boolean;
  'aria-label'?: string;
}

/**
 * Baydaq date field: opens the Baydaq calendar instead of the browser's picker, shows the date in
 * the page language and the Hijri (Umm al-Qura) date of the selection.
 */
export default function DatePicker({ id, value, onChange, min, max, placeholder, className, disabled, invalid, clearable = true, 'aria-label': ariaLabel }: DatePickerProps) {
  const t = useT();
  const { locale, dir } = useLocale();
  const [open, setOpen] = useState(false);
  const date = parseIsoDate(value);
  const today = todayIso();
  const choose = (next: string) => {
    onChange(next);
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <PickerTrigger
          id={id} className={className} disabled={disabled} invalid={invalid} aria-label={ariaLabel}
          icon={<CalendarDays />} placeholder={placeholder ?? t('common.ui.choose_date')} display={date ? formatDay(date, locale) : null}
        />
      </PopoverTrigger>
      <PopoverContent dir={dir} className="w-auto">
        <Calendar value={value} onSelect={choose} min={min} max={max} />
        <div className="mt-2 flex items-center justify-between gap-2 border-t pt-2">
          <span className="text-xs text-muted-foreground">{date ? formatHijri(date, locale) : ''}</span>
          <span className="flex gap-1">
            {clearable && value && <Button type="button" size="xs" variant="ghost" onClick={() => choose('')}>{t('common.ui.clear')}</Button>}
            {withinRange(parseIsoDate(today)!, min, max) && <Button type="button" size="xs" variant="outline" onClick={() => choose(today)}>{t('common.ui.today')}</Button>}
          </span>
        </div>
      </PopoverContent>
    </Popover>
  );
}
