import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import PageHeader from '@/components/platform/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

export interface CalendarEvent { key: string; category: string; title: string; date: string; endDate: string | null; url: string | null; tone: 'info' | 'good' | 'warn' | 'bad' }

interface Props { month: string; from: string; to: string; today: string; events: CalendarEvent[]; categories: string[] }

export const TONES: Record<CalendarEvent['tone'], string> = {
  info: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
  good: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
  warn: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
  bad: 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

/** Dates as yyyy-mm-dd strings, day by day, without time-zone shifts. */
function days(from: string, to: string): string[] {
  const out: string[] = [];
  const cursor = new Date(`${from}T00:00:00Z`);
  const end = new Date(`${to}T00:00:00Z`);

  while (cursor <= end) {
    out.push(cursor.toISOString().slice(0, 10));
    cursor.setUTCDate(cursor.getUTCDate() + 1);
  }

  return out;
}

function shiftMonth(month: string, by: number): string {
  const [year, number] = month.split('-').map(Number);
  const date = new Date(Date.UTC(year, number - 1 + by, 1));

  return date.toISOString().slice(0, 7);
}

/** One month of everything with a date, from every module, with a filter by kind. */
export default function Calendar({ month, from, to, today, events, categories }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const c = (key: string, replace?: Record<string, string>) => t(`suite.calendar.${key}`, replace);
  const [hidden, setHidden] = useState<string[]>([]);
  const go = (target: string) => router.get(url('/calendar'), { month: target }, { preserveState: true, preserveScroll: true });

  const byDay = useMemo(() => {
    const map: Record<string, CalendarEvent[]> = {};

    for (const event of events.filter((item) => !hidden.includes(item.category))) {
      for (const day of days(event.date < from ? from : event.date, (event.endDate ?? event.date) > to ? to : (event.endDate ?? event.date))) {
        (map[day] ??= []).push(event);
      }
    }

    return map;
  }, [events, hidden, from, to]);

  const title = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month}-01T00:00:00Z`));

  return (
    <SuiteLayout title={c('title')}>
      <PageHeader
        title={c('title')}
        description={c('lede')}
        actions={(
          <div className="flex items-center gap-1">
            <Button size="icon" variant="outline" onClick={() => go(shiftMonth(month, -1))}><ChevronRight className="ltr:rotate-180" /></Button>
            <Button variant="outline" onClick={() => go(today.slice(0, 7))}>{c('today')}</Button>
            <Button size="icon" variant="outline" onClick={() => go(shiftMonth(month, 1))}><ChevronLeft className="ltr:rotate-180" /></Button>
          </div>
        )}
      />
      <div className="mb-3 flex flex-wrap items-center gap-1">
        <span className="me-2 font-semibold">{title}</span>
        {categories.map((category) => (
          <button key={category} type="button" onClick={() => setHidden(hidden.includes(category) ? hidden.filter((item) => item !== category) : [...hidden, category])} className={cn('rounded-full border px-2.5 py-0.5 text-xs', hidden.includes(category) ? 'opacity-40' : 'bg-muted')}>
            {c(`categories.${category}`)}
          </button>
        ))}
      </div>
      {events.length === 0 && <p className="mb-3 text-sm text-muted-foreground">{c('nothing')}</p>}
      <Card>
        <CardContent className="p-0">
          <div className="grid grid-cols-7 border-b text-center text-xs text-muted-foreground">
            {[0, 1, 2, 3, 4, 5, 6].map((day) => <div key={day} className="py-2">{t(`suite.calendar.weekdays.${day}`)}</div>)}
          </div>
          <div className="grid grid-cols-7">
            {days(from, to).map((day) => {
              const items = byDay[day] ?? [];

              return (
                <div key={day} className={cn('min-h-24 border-b border-e p-1 text-xs', !day.startsWith(month) && 'bg-muted/40 text-muted-foreground')}>
                  <div className={cn('mb-1 inline-flex size-6 items-center justify-center rounded-full', day === today && 'bg-primary text-primary-foreground')}>{Number(day.slice(8))}</div>
                  <div className="flex flex-col gap-0.5">
                    {items.slice(0, 4).map((event) => {
                      const body = <span className={cn('block truncate rounded px-1 py-0.5', TONES[event.tone])} title={event.title}>{event.title}</span>;

                      return event.url ? <Link key={event.key} href={url(event.url)}>{body}</Link> : <span key={event.key}>{body}</span>;
                    })}
                    {items.length > 4 && <span className="px-1 text-muted-foreground">{c('more', { count: String(items.length - 4) })}</span>}
                  </div>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
