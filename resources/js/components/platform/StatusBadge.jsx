import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// Colour per status value; anything not listed gets the neutral outline.
const TONES = {
  active: 'bg-emerald-100 text-emerald-800',
  live: 'bg-emerald-100 text-emerald-800',
  paid: 'bg-emerald-100 text-emerald-800',
  approved: 'bg-emerald-100 text-emerald-800',
  in_development: 'bg-sky-100 text-sky-800',
  sent: 'bg-sky-100 text-sky-800',
  reviewing: 'bg-sky-100 text-sky-800',
  new: 'bg-brass/20 text-walnut',
  planning: 'bg-brass/20 text-walnut',
  draft: 'bg-muted text-muted-foreground',
  paused: 'bg-amber-100 text-amber-800',
  overdue: 'bg-red-100 text-red-800',
  rejected: 'bg-red-100 text-red-800',
  void: 'bg-muted text-muted-foreground line-through',
};

/** A status pill with the translated enum label (enums group in the lang files, group = the enum's LABELS). */
export default function StatusBadge({ group, value, className }) {
  const t = useT();

  if (!value) {
    return null;
  }

  return (
    <Badge variant="outline" className={cn('border-transparent', TONES[value], className)}>
      {t(`enums.${group}.${value}`)}
    </Badge>
  );
}
