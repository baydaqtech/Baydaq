import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/** A dashboard number with its label and icon. */
export default function StatCard({ label, value, icon: Icon, tone, href }) {
  const body = (
    <Card className={cn('gap-0 py-0 transition-colors', href && 'hover:border-brass/60')}>
      <CardContent className="flex items-center gap-4 p-4">
        {Icon && (
          <span className={cn('grid size-10 shrink-0 place-items-center rounded-full bg-brass/15 text-walnut', tone)}>
            <Icon className="size-5" />
          </span>
        )}
        <div className="min-w-0">
          <div className="text-sm text-muted-foreground">{label}</div>
          <div className="truncate text-xl font-semibold">{value}</div>
        </div>
      </CardContent>
    </Card>
  );

  return href ? <Link href={href} className="block">{body}</Link> : body;
}
