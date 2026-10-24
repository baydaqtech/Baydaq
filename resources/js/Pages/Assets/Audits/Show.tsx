import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface Item { id: number; asset_id: number; tag: string; name: string; serial: string | null; status: string; scanned_at: string | null }
interface AuditInfo { id: number; branch: string | null; started_on: string; closed_at: string | null; note: string | null }

const GROUPS = ['expected', 'missing', 'unexpected', 'found'] as const;

/** Scan tags one after another; the lists update as items are ticked off. */
export default function AuditShow({ audit, items, can_close: canClose }: { audit: AuditInfo; items: Item[]; can_close: boolean }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const flash = usePage().flash as { scanned?: { tag: string; name: string; status: string } };
  const [last, setLast] = useState(flash.scanned ?? null);
  const [markLost, setMarkLost] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  const form = useForm({ tag: '' });
  const open = audit.closed_at === null;

  useEffect(() => input.current?.focus(), [last]);

  function scan(event: React.FormEvent) {
    event.preventDefault();
    form.post(url(`/assets/audits/${audit.id}/scan`), { preserveScroll: true, onSuccess: (page) => { setLast((page.flash as typeof flash).scanned ?? null); form.reset(); }, onError: () => input.current?.select() });
  }

  return (
    <SuiteLayout title={`${t('assets.audits.title')} · ${audit.branch}`}>
      <PageHeader title={`${t('assets.audits.title')} · ${audit.branch}`} description={<span dir="ltr">{audit.started_on}{audit.closed_at ? ` → ${audit.closed_at}` : ''}</span>} />
      {open && can('assets.audits.create') && (
        <form onSubmit={scan} className="mb-2 flex max-w-xl gap-2">
          <Input ref={input} dir="ltr" autoFocus autoComplete="off" className="h-11 font-mono uppercase" placeholder={t('assets.audits.scan')} aria-label={t('assets.audits.scan')} value={form.data.tag} onChange={(event) => form.setData('tag', event.target.value)} />
          <Button type="submit" size="lg" className="h-11" disabled={form.processing || !form.data.tag}>{t('assets.audits.scan_submit')}</Button>
        </form>
      )}
      {form.errors.tag && <p className="mb-2 text-sm text-destructive">{form.errors.tag}</p>}
      {last && <p className={cn('mb-4 text-sm', last.status === 'found' ? 'text-emerald-700' : 'text-amber-700')}><span dir="ltr" className="font-mono">{last.tag}</span> {last.name} · {t(`assets.audit_item_status.${last.status}`)}</p>}

      <div className="grid gap-4 md:grid-cols-2">
        {GROUPS.map((group) => {
          const rows = items.filter((item) => item.status === group);
          if (rows.length === 0) {
            return null;
          }
          return (
            <section key={group}>
              <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{t(`assets.audit_item_status.${group}`)} ({rows.length})</h2>
              <ul className="flex flex-col divide-y rounded-md border">
                {rows.map((item) => (
                  <li key={item.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                    <Link href={url(`/assets/assets/${item.asset_id}`)} className="hover:underline"><span dir="ltr" className="font-mono text-xs">{item.tag}</span> {item.name}</Link>
                    <StatusBadge file="assets" group="audit_item_status" value={item.status} />
                  </li>
                ))}
              </ul>
            </section>
          );
        })}
      </div>

      {open && canClose && (
        <div className="mt-6 flex flex-wrap items-center gap-4 border-t pt-4">
          <label className="flex items-center gap-2 text-sm"><Checkbox checked={markLost} onCheckedChange={(value) => setMarkLost(value === true)} />{t('assets.audits.mark_lost')}</label>
          <Button variant="outline" onClick={() => router.post(url(`/assets/audits/${audit.id}/close`), { mark_missing_lost: markLost }, { preserveScroll: true })}>{t('assets.audits.close')}</Button>
        </div>
      )}
    </SuiteLayout>
  );
}
