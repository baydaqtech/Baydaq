import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface FeedItem { id: string; kind: string; title: string; body: string; url: string | null; read: boolean; at: string }

interface SharedProps {
  notifications: { unread: number } | null;
  notification_feed?: FeedItem[];
  [key: string]: unknown;
}

function when(at: string, locale: string): string {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(at));
}

/**
 * The notification centre: the unread count on the bell, the latest alerts when opened (loaded
 * only then), each opening its page and counting as read.
 */
export default function NotificationBell() {
  const t = useT();
  const { locale, dir } = useLocale();
  const url = useSuiteUrl();
  const { notifications, notification_feed: feed } = usePage<SharedProps>().props;
  const [loading, setLoading] = useState(false);
  const n = (key: string, replace?: Record<string, string>) => t(`common.notifications.${key}`, replace);
  const unread = notifications?.unread ?? 0;

  function load(open: boolean) {
    if (open) {
      router.reload({ only: ['notification_feed', 'notifications'], onStart: () => setLoading(true), onFinish: () => setLoading(false) });
    }
  }

  function open(item: FeedItem) {
    router.post('/notifications/read', { id: item.id }, {
      preserveScroll: true,
      onSuccess: () => item.url && router.visit(url(item.url)),
    });
  }

  return (
    <Popover onOpenChange={load}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label={unread > 0 ? n('unread', { count: String(unread) }) : n('title')}>
          <Bell />
          {unread > 0 && (
            <span className="absolute -end-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white tabular-nums">
              {unread > 99 ? '99+' : unread}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" dir={dir} className="w-80 p-0">
        <div className="flex items-center justify-between border-b px-3 py-2">
          <span className="text-sm font-semibold">{n('title')}</span>
          <div className="flex items-center gap-1">
            {unread > 0 && <Button size="xs" variant="ghost" onClick={() => router.post('/notifications/read', {}, { preserveScroll: true })}>{n('mark_all')}</Button>}
            <Button asChild size="icon-xs" variant="ghost" aria-label={n('settings')}><Link href="/account/notifications"><Settings2 /></Link></Button>
          </div>
        </div>
        <div className="max-h-96 overflow-y-auto">
          {loading && !feed ? (
            <div className="flex flex-col gap-2 p-3">{[0, 1, 2].map((key) => <Skeleton key={key} className="h-10 w-full" />)}</div>
          ) : !feed || feed.length === 0 ? (
            <p className="px-3 py-8 text-center text-sm text-muted-foreground">{n('none')}</p>
          ) : (
            <ul className="divide-y">
              {feed.map((item) => (
                <li key={item.id}>
                  <button type="button" onClick={() => open(item)} className={cn('flex w-full flex-col gap-0.5 px-3 py-2 text-start text-sm transition-colors hover:bg-muted', !item.read && 'bg-accent/40')}>
                    <span className="flex items-center gap-2 font-medium">
                      {!item.read && <span className="size-1.5 shrink-0 rounded-full bg-primary" />}
                      {item.title}
                    </span>
                    <span className="text-muted-foreground">{item.body}</span>
                    <span className="text-xs text-muted-foreground">{when(item.at, locale)}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
