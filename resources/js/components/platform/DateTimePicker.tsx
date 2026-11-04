import DatePicker from '@/components/platform/DatePicker';
import TimeField from '@/components/platform/TimeField';
import { cn } from '@/lib/utils';

interface Props {
  id?: string;
  /** "YYYY-MM-DDTHH:MM", or empty for none. */
  value?: string | null;
  onChange: (value: string) => void;
  min?: string | null;
  className?: string;
  invalid?: boolean;
}

/** A date and a time side by side, joined into one "YYYY-MM-DDTHH:MM" value. */
export default function DateTimePicker({ id, value, onChange, min, className, invalid }: Props) {
  const date = value?.slice(0, 10) ?? '';
  const time = value?.slice(11, 16) ?? '';
  const join = (nextDate: string, nextTime: string) => onChange(nextDate ? `${nextDate}T${nextTime || '09:00'}` : '');

  return (
    <div className={cn('flex gap-2', className)}>
      <DatePicker id={id} value={date} onChange={(next) => join(next, time)} min={min?.slice(0, 10)} invalid={invalid} className="flex-1" />
      <TimeField value={time} onChange={(next) => join(date || new Date().toISOString().slice(0, 10), next)} invalid={invalid} className="w-28" />
    </div>
  );
}
