import { useState } from 'react';
import { CalendarRange } from 'lucide-react';
import Calendar from '@/components/platform/Calendar';
import PickerTrigger from '@/components/platform/PickerTrigger';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDay, parseIsoDate, todayIso } from '@/lib/dates';
import { useLocale, useT } from '@/lib/i18n';

interface Props {
  id?: string;
  /** "YYYY-MM", or empty for none. */
  value?: string | null;
  onChange: (value: string) => void;
  min?: string | null;
  max?: string | null;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  invalid?: boolean;
  'aria-label'?: string;
}

/** Baydaq month field ("YYYY-MM"): a year of months, then a dozen years, no browser picker. */
export default function MonthPicker({ id, value, onChange, min, max, placeholder, className, disabled, invalid, 'aria-label': ariaLabel }: Props) {
  const t = useT();
  const { locale, dir } = useLocale();
  const [open, setOpen] = useState(false);
  const month = parseIsoDate(value ? `${value.slice(0, 7)}-01` : null);
  const choose = (next: string) => {
    onChange(next);
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <PickerTrigger
          id={id} className={className} disabled={disabled} invalid={invalid} aria-label={ariaLabel}
          icon={<CalendarRange />} placeholder={placeholder ?? t('common.ui.choose_month')} display={month ? formatDay(month, locale, { month: 'long', year: 'numeric' }) : null}
        />
      </PopoverTrigger>
      <PopoverContent dir={dir} className="w-auto">
        <Calendar mode="month" value={value} onSelect={choose} min={min} max={max} />
        <div className="mt-2 flex justify-end border-t pt-2">
          <Button type="button" size="xs" variant="outline" onClick={() => choose(todayIso().slice(0, 7))}>{t('common.ui.this_month')}</Button>
        </div>
      </PopoverContent>
    </Popover>
  );
}
