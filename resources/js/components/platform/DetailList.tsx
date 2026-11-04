import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export interface DetailItem {
  label: string;
  value: ReactNode;
  wide?: boolean;
}

/** Label/value pairs; empty values show a dash. */
export default function DetailList({ items, className }: { items: (DetailItem | false | null | undefined)[]; className?: string }) {
  return (
    <dl className={cn('grid gap-x-6 gap-y-3 sm:grid-cols-2', className)}>
      {items.filter((item): item is DetailItem => Boolean(item)).map(({ label, value, wide }) => (
        <div key={label} className={cn('min-w-0', wide && 'sm:col-span-2')}>
          <dt className="text-xs text-muted-foreground">{label}</dt>
          <dd className="mt-0.5 text-sm break-words">{value === null || value === undefined || value === '' ? '—' : value}</dd>
        </div>
      ))}
    </dl>
  );
}
