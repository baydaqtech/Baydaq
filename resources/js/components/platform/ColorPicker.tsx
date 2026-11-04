import { useState } from 'react';
import { Check } from 'lucide-react';
import PickerTrigger from '@/components/platform/PickerTrigger';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Baydaq brand colours first, then a calm palette that reads well on light and dark. */
const SWATCHES = [
  '#3B2414', '#6B5238', '#C8902F', '#C8A84B', '#B45309', '#DC2626', '#EA580C', '#D97706',
  '#16A34A', '#0F766E', '#0891B2', '#2563EB', '#4F46E5', '#7C3AED', '#C026D3', '#DB2777',
  '#475569', '#64748B', '#111827', '#FFFFFF',
];

const HEX = /^#[0-9a-f]{6}$/i;

interface Props {
  id?: string;
  value?: string | null;
  onChange: (value: string) => void;
  className?: string;
  invalid?: boolean;
  'aria-label'?: string;
}

/** Baydaq colour field: palette and a colour code, instead of the browser's colour dialog. */
export default function ColorPicker({ id, value, onChange, className, invalid, 'aria-label': ariaLabel }: Props) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState(value ?? '');
  const current = value && HEX.test(value) ? value.toUpperCase() : null;

  return (
    <Popover open={open} onOpenChange={(next) => { setOpen(next); setDraft(value ?? ''); }}>
      <PopoverTrigger asChild>
        <PickerTrigger
          id={id} className={className} invalid={invalid} aria-label={ariaLabel} placeholder={t('common.actions.choose')} display={current}
          icon={<span className="size-4 shrink-0 rounded-sm border" style={{ backgroundColor: current ?? 'transparent' }} />}
        />
      </PopoverTrigger>
      <PopoverContent dir={dir} className="w-56">
        <div className="grid grid-cols-5 gap-1.5">
          {SWATCHES.map((swatch) => (
            <button
              key={swatch} type="button" aria-label={swatch} onClick={() => { onChange(swatch); setOpen(false); }}
              className="flex size-8 items-center justify-center rounded-md border outline-none focus-visible:ring-3 focus-visible:ring-ring/50" style={{ backgroundColor: swatch }}
            >
              {current === swatch && <Check className={cn('size-4', swatch === '#FFFFFF' ? 'text-foreground' : 'text-white')} />}
            </button>
          ))}
        </div>
        <label className="mt-3 flex flex-col gap-1 text-xs text-muted-foreground">
          {t('common.ui.custom_color')}
          <Input
            dir="ltr" value={draft} maxLength={7} placeholder="#C8902F" aria-invalid={draft !== '' && !HEX.test(draft)}
            onChange={(event) => { setDraft(event.target.value); if (HEX.test(event.target.value)) { onChange(event.target.value.toUpperCase()); } }}
          />
        </label>
      </PopoverContent>
    </Popover>
  );
}
