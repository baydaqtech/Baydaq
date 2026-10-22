import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { useLocale, useT } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

/** "1–20 of 45" with previous/next, from a Laravel paginated resource ({ meta, links }). */
export default function Paginator({ meta, links }) {
  const t = useT();
  const { locale } = useLocale();

  if (!meta || meta.last_page <= 1) {
    return null;
  }

  const pageLink = (href, label, Icon, iconSide) => (href ? (
    <Link href={href} preserveScroll className={buttonVariants({ variant: 'outline', size: 'sm' })}>
      {iconSide === 'start' && <Icon className="rtl:rotate-180" />}
      {label}
      {iconSide === 'end' && <Icon className="rtl:rotate-180" />}
    </Link>
  ) : (
    <span className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'pointer-events-none opacity-40')} aria-disabled="true">
      {iconSide === 'start' && <Icon className="rtl:rotate-180" />}
      {label}
      {iconSide === 'end' && <Icon className="rtl:rotate-180" />}
    </span>
  ));

  return (
    <nav className="flex items-center justify-between gap-3 border-t px-4 py-3 text-sm text-muted-foreground" aria-label="pagination">
      <span>
        {t('admin.common.showing', {
          from: formatNumber(meta.from ?? 0, locale),
          to: formatNumber(meta.to ?? 0, locale),
          total: formatNumber(meta.total, locale),
        })}
      </span>
      <div className="flex gap-2">
        {pageLink(links.prev, t('admin.common.previous'), ChevronLeft, 'start')}
        {pageLink(links.next, t('admin.common.next'), ChevronRight, 'end')}
      </div>
    </nav>
  );
}
