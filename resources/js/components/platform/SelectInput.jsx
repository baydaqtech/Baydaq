import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// Radix selects cannot hold an empty value, so "nothing chosen" travels as this marker.
const NONE = '__none';

/**
 * Controlled select over [{ value, label }]. `emptyLabel` adds a first option meaning "none/all"
 * that maps to an empty string.
 */
export default function SelectInput({ id, value, onChange, options, emptyLabel, placeholder, invalid, className }) {
  const t = useT();
  const { dir } = useLocale();
  const current = value === '' || value === null || value === undefined ? (emptyLabel ? NONE : undefined) : String(value);

  return (
    <Select dir={dir} value={current} onValueChange={(next) => onChange(next === NONE ? '' : next)}>
      <SelectTrigger id={id} aria-invalid={invalid || undefined} className={cn('w-full', className)}>
        <SelectValue placeholder={placeholder ?? t('admin.common.choose')} />
      </SelectTrigger>
      <SelectContent dir={dir}>
        {emptyLabel && <SelectItem value={NONE}>{emptyLabel}</SelectItem>}
        {options.map((option) => (
          <SelectItem key={option.value} value={String(option.value)}>{option.label}</SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
