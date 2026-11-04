import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Check, ExternalLink, Inbox } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface Decision { method: 'post' | 'put'; url: string; data: Record<string, unknown>; noteField: string | null }

interface ApprovalItem {
  key: string;
  kind: string;
  title: string;
  detail: string;
  since: string;
  date: string | null;
  endDate: string | null;
  amount: string | null;
  url: string | null;
  approve: Decision | null;
  reject: Decision | null;
}

/** "3 days", "5 hours", "a minute" since a moment, in the page's language. */
function waited(since: string, locale: string): string {
  const minutes = Math.max(1, Math.round((Date.now() - new Date(since).getTime()) / 60000));
  const [value, unit] = minutes < 60 ? [minutes, 'minute'] : minutes < 1440 ? [Math.round(minutes / 60), 'hour'] : [Math.round(minutes / 1440), 'day'];

  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-GB', { style: 'unit', unit, unitDisplay: 'long' }).format(value);
}

/** Everything waiting on my decision, from every module, oldest first; decide in place or open the record. */
export default function Approvals({ items, kinds }: { items: ApprovalItem[]; kinds: string[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const a = (key: string, replace?: Record<string, string>) => t(`suite.approvals.${key}`, replace);
  const [kind, setKind] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const shown = useMemo(() => items.filter((item) => kind === null || item.kind === kind), [items, kind]);
  const count = (value: string) => items.filter((item) => item.kind === value).length;

  function approve(item: ApprovalItem) {
    if (!item.approve) {
      return;
    }

    router.visit(url(item.approve.url), {
      method: item.approve.method,
      data: item.approve.data as never,
      preserveScroll: true,
      onStart: () => setBusy(item.key),
      onFinish: () => setBusy(null),
    });
  }

  return (
    <SuiteLayout title={a('title')}>
      <PageHeader title={a('title')} description={a('lede')} />

      {items.length > 0 && (
        <div className="mb-3 flex flex-wrap gap-1" role="tablist">
          {[null, ...kinds].map((value) => (
            <button
              key={value ?? 'all'}
              type="button"
              role="tab"
              aria-selected={kind === value}
              onClick={() => setKind(value)}
              className={cn('rounded-full border px-2.5 py-0.5 text-xs transition-colors', kind === value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted')}
            >
              {value === null ? `${a('all')} · ${items.length}` : `${a(`kinds.${value}`)} · ${count(value)}`}
            </button>
          ))}
        </div>
      )}

      <Card>
        <CardContent className="p-0">
          {shown.length === 0 ? (
            <div className="flex flex-col items-center gap-2 px-4 py-14 text-center">
              <Inbox className="size-10 text-muted-foreground" />
              <p className="font-medium">{a('nothing')}</p>
              <p className="text-sm text-muted-foreground">{a('nothing_hint')}</p>
            </div>
          ) : (
            <ul className="divide-y">
              {shown.map((item) => (
                <li key={item.key} className="flex flex-wrap items-center gap-3 px-4 py-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant="outline">{a(`kinds.${item.kind}`)}</Badge>
                      <span className="font-medium">{item.title}</span>
                      {item.amount !== null && <span className="tabular-nums">{formatMoney(item.amount, locale)}</span>}
                    </div>
                    <p className="mt-0.5 truncate text-sm text-muted-foreground">
                      {[
                        item.detail,
                        item.date && (item.endDate && item.endDate !== item.date ? `${formatDate(item.date, locale)} → ${formatDate(item.endDate, locale)}` : formatDate(item.date, locale)),
                        a('waiting', { time: waited(item.since, locale) }),
                      ].filter(Boolean).join(' · ')}
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    {item.reject && (
                      <ConfirmAction
                        href={url(item.reject.url)}
                        method={item.reject.method}
                        data={item.reject.data}
                        note={item.reject.noteField ? { field: item.reject.noteField, label: a('reject_note') } : undefined}
                        variant="ghost"
                        size="sm"
                        title={a('reject_title')}
                        description={`${a(`kinds.${item.kind}`)} · ${item.title}. ${a('reject_hint')}`}
                        confirmLabel={a('reject')}
                      >
                        {a('reject')}
                      </ConfirmAction>
                    )}
                    {item.approve && (
                      <Button size="sm" disabled={busy === item.key} onClick={() => approve(item)}><Check data-icon="inline-start" />{a('approve')}</Button>
                    )}
                    {item.url && (
                      <Button asChild size="icon-sm" variant="ghost" aria-label={a('open')}><Link href={url(item.url)}><ExternalLink /></Link></Button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
