import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, type LucideIcon } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { useLocale, useT } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LaravelPage, Paginated } from '@/types/resources';

type Props = { page: Paginated<unknown> | LaravelPage<unknown> };

/**
 * "1–20 of 45" with previous/next, from a paginated resource ({ meta, links }) or a paginator
 * sent as is (flat fields).
 */
export default function Paginator({ page }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const meta = 'meta' in page ? page.meta : page;
  const links = 'meta' in page ? page.links : { prev: page.prev_page_url, next: page.next_page_url };

  if (!meta || meta.last_page <= 1) {
    return null;
  }

  const pageLink = (href: string | null, label: string, Icon: LucideIcon, iconSide: 'start' | 'end') => (href ? (
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
        {t('common.actions.showing', {
          from: formatNumber(meta.from ?? 0, locale),
          to: formatNumber(meta.to ?? 0, locale),
          total: formatNumber(meta.total, locale),
        })}
      </span>
      <div className="flex gap-2">
        {pageLink(links.prev, t('common.actions.previous'), ChevronLeft, 'start')}
        {pageLink(links.next, t('common.actions.next'), ChevronRight, 'end')}
      </div>
    </nav>
  );
}
