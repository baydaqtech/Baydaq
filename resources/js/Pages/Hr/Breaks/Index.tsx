import { useEffect, useState } from 'react';
import { router, useForm, usePoll } from '@inertiajs/react';
import { AlarmClock, Coffee, Settings2 } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface BreakRow { id: number; employee: string | null; branch: string | null; kind: string; status: string; minutes: number; starts_at: string | null; back_at: string | null; ends_at: string | null; overdue: boolean; note: string | null }
interface Working { id: number; name: string; branch: string | null; worked_minutes: number | null }
interface Props {
  on_break: BreakRow[]; requests: BreakRow[]; working: Working[]; ended: BreakRow[];
  settings: { minutes: number; max_concurrent: number; self_start: boolean }; can_manage: boolean;
}

function clock(iso: string | null, locale: string): string {
  return iso ? new Date(iso).toLocaleTimeString(locale === 'ar' ? 'ar-SA' : 'en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Riyadh' }) : '';
}

/** Minutes left until back (negative when late), ticking every 20 seconds. */
function useMinutesLeft(backAt: string | null): number | null {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => { const timer = window.setInterval(() => setNow(Date.now()), 20000); return () => window.clearInterval(timer); }, []);
  return backAt ? Math.round((new Date(backAt).getTime() - now) / 60000) : null;
}

function OnBreakRow({ row, canManage }: { row: BreakRow; canManage: boolean }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const left = useMinutesLeft(row.back_at);
  const late = left !== null && left < 0;

  return (
    <li className={cn('flex flex-wrap items-center justify-between gap-2 rounded-md border p-2', late && 'border-red-300 bg-red-50')}>
      <div className="min-w-0">
        <div className="font-medium">{row.employee}</div>
        <div className="text-xs text-muted-foreground">{row.branch} · {t(`hr.break_kind.${row.kind}`)} · {clock(row.starts_at, locale)}</div>
      </div>
      <div className="flex items-center gap-2">
        <span className={cn('text-sm', late ? 'font-semibold text-red-700' : 'text-muted-foreground')}>
          {late ? t('hr.breaks.late', { minutes: -left }) : t('hr.breaks.back_at', { time: clock(row.back_at, locale) })}
        </span>
        {canManage && <Button size="xs" variant="outline" onClick={() => router.post(url(`/hr/breaks/${row.id}/end`), {}, { preserveScroll: true })}>{t('hr.breaks.end')}</Button>}
      </div>
    </li>
  );
}

function SettingsDialog({ settings }: { settings: Props['settings'] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ minutes: String(settings.minutes), max_concurrent: String(settings.max_concurrent), self_start: settings.self_start });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><Settings2 data-icon="inline-start" />{t('hr.breaks.settings')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form className="flex flex-col gap-4" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/breaks/settings'), { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
          <DialogHeader><DialogTitle>{t('hr.breaks.settings')}</DialogTitle></DialogHeader>
          <FormField label={t('hr.breaks.minutes')} error={form.errors.minutes}><Input type="number" min="15" max="120" dir="ltr" value={form.data.minutes} onChange={(event) => form.setData('minutes', event.target.value)} /></FormField>
          <FormField label={t('hr.breaks.max_concurrent')} error={form.errors.max_concurrent}><Input type="number" min="0" dir="ltr" value={form.data.max_concurrent} onChange={(event) => form.setData('max_concurrent', event.target.value)} /></FormField>
          <label className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.self_start} onCheckedChange={(state) => form.setData('self_start', state === true)} />{t('hr.breaks.self_start')}</label>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** The live break board, refreshed every 30 seconds. */
export default function BreaksIndex({ on_break: onBreak, requests, working, ended, settings, can_manage: canManage }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { isOwner } = useAccess();
  usePoll(30000, { only: ['on_break', 'requests', 'working', 'ended'] }, { keepAlive: true });
  const post = (path: string, data: Record<string, string | number | boolean> = {}) => router.post(url(path), data, { preserveScroll: true });

  return (
    <SuiteLayout title={t('hr.breaks.title')}>
      <PageHeader title={t('hr.breaks.title')} description={t('hr.breaks.lede')} actions={canManage && isOwner && <SettingsDialog settings={settings} />} />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><Coffee className="size-4" />{t('hr.breaks.on_break')} <Badge variant="outline">{onBreak.length}</Badge></CardTitle></CardHeader>
          <CardContent>
            {onBreak.length === 0 && <p className="text-sm text-muted-foreground">{t('hr.breaks.none')}</p>}
            <ul className="flex flex-col gap-2">{onBreak.map((row) => <OnBreakRow key={row.id} row={row} canManage={canManage} />)}</ul>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>{t('hr.breaks.requests')} <Badge variant="outline">{requests.length}</Badge></CardTitle></CardHeader>
          <CardContent>
            {requests.length === 0 && <p className="text-sm text-muted-foreground">{t('hr.breaks.none')}</p>}
            <ul className="flex flex-col gap-2">
              {requests.map((row) => (
                <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2">
                  <div><div className="font-medium">{row.employee}</div><div className="text-xs text-muted-foreground">{row.branch} · {row.minutes} min{row.note && ` · ${row.note}`}</div></div>
                  {canManage && <div className="flex gap-1"><Button size="xs" onClick={() => post(`/hr/breaks/${row.id}/decide`, { approve: true })}>{t('hr.breaks.approve')}</Button><Button size="xs" variant="ghost" onClick={() => post(`/hr/breaks/${row.id}/decide`, { approve: false })}>{t('hr.breaks.refuse')}</Button></div>}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle>{t('hr.breaks.working')} <Badge variant="outline">{working.length}</Badge></CardTitle></CardHeader>
          <CardContent>
            {working.length === 0 && <p className="text-sm text-muted-foreground">{t('hr.breaks.none')}</p>}
            <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {working.map((row) => (
                <li key={row.id} className={cn('flex items-center justify-between gap-2 rounded-md border p-2', row.worked_minutes !== null && 'border-amber-300 bg-amber-50')}>
                  <div className="min-w-0">
                    <div className="truncate font-medium">{row.name}</div>
                    <div className="text-xs text-muted-foreground">{row.branch}{row.worked_minutes !== null && <span className="ms-1 inline-flex items-center gap-1 font-medium text-amber-700"><AlarmClock className="size-3" />{t('hr.breaks.needs_break', { hours: Math.floor(row.worked_minutes / 60) })}</span>}</div>
                  </div>
                  {canManage && <Button size="xs" variant="outline" onClick={() => post(`/hr/breaks/employees/${row.id}`, { minutes: settings.minutes })}>{t('hr.breaks.give')}</Button>}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
        {ended.length > 0 && (
          <Card className="lg:col-span-2">
            <CardHeader><CardTitle>{t('hr.breaks.ended')}</CardTitle></CardHeader>
            <CardContent>
              <ul className="divide-y text-sm">
                {ended.map((row) => <li key={row.id} className="flex justify-between gap-2 py-1.5"><span>{row.employee} · <span className="text-muted-foreground">{row.branch}</span></span><span dir="ltr" className="text-muted-foreground">{clock(row.starts_at, locale)}–{clock(row.ends_at, locale)}</span></li>)}
              </ul>
            </CardContent>
          </Card>
        )}
      </div>
    </SuiteLayout>
  );
}
