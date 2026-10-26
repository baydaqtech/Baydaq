import { Link, router, useForm } from '@inertiajs/react';
import { Check, DoorOpen } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface ChecklistItem { key: string; done: string | null; auto: boolean; blockers?: string[] }

interface Separation {
  id: number; employee_id: number; employee: string; employee_number: string; company: string | null; kind: string; status: string; source: string;
  submitted_on: string; last_day: string; deemed_accept_on: string | null; deemed: boolean; reason: string | null; checklist: ChecklistItem[]; can_decide: boolean;
}

interface Props {
  separations: Separation[];
  view: 'open' | 'history';
  max_defer_days: number;
  kinds: string[];
  employees: Option[];
}

function NewSeparation({ employees, kinds }: { employees: Option[]; kinds: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const s = (key: string) => t(`hr.separations.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ employee_id: '', kind: 'resignation', submitted_on: new Date().toISOString().slice(0, 10), last_day: '', reason: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><DoorOpen />{s('new')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{s('new')}</DialogTitle></DialogHeader>
        <form
          id="new-separation"
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event) => { event.preventDefault(); form.transform(({ employee_id: _employee, ...data }) => data); form.post(url(`/hr/employees/${form.data.employee_id}/separations`), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}
        >
          <FormField label={t('hr.violations.employee')} className="sm:col-span-2"><SelectInput value={form.data.employee_id} onChange={(value) => form.setData('employee_id', value)} options={employees} /></FormField>
          <FormField label={s('kind')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds.map((value) => ({ value, label: s(`kinds.${value}`) }))} /></FormField>
          <FormField label={s('submitted_on')} error={form.errors.submitted_on}><Input type="date" dir="ltr" value={form.data.submitted_on} onChange={(event) => form.setData('submitted_on', event.target.value)} /></FormField>
          <FormField label={s('last_day')} error={form.errors.last_day}><Input type="date" dir="ltr" value={form.data.last_day} onChange={(event) => form.setData('last_day', event.target.value)} /></FormField>
          <FormField label={s('reason')} error={form.errors.reason} className="sm:col-span-2"><Textarea rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="new-separation" disabled={form.processing || form.data.employee_id === ''}>{s('new')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function SeparationCard({ separation, maxDeferDays }: { separation: Separation; maxDeferDays: number }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const s = (key: string, replace?: Record<string, string>) => t(`hr.separations.${key}`, replace);
  const [deferTo, setDeferTo] = useState('');
  const act = (action: string, extra: Record<string, unknown> = {}) => router.put(url(`/hr/separations/${separation.id}`), { action, ...extra }, { preserveScroll: true });
  const maxDefer = new Date(new Date(separation.submitted_on).getTime() + maxDeferDays * 86400000).toISOString().slice(0, 10);
  const waiting = ['submitted', 'deferred'].includes(separation.status);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center justify-between gap-2">
          <Link href={url(`/hr/employees/${separation.employee_id}`)} className="hover:underline">{separation.employee} <span className="text-xs font-normal text-muted-foreground" dir="ltr">{separation.employee_number}</span></Link>
          <span className="flex gap-1"><Badge variant="outline">{s(`kinds.${separation.kind}`)}</Badge><Badge variant={separation.status === 'accepted' ? 'default' : 'secondary'}>{s(`statuses.${separation.status}`)}</Badge></span>
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <div className="grid gap-1 sm:grid-cols-3">
          <div><span className="text-muted-foreground">{s('submitted_on')}: </span>{formatDate(separation.submitted_on, locale)}</div>
          <div><span className="text-muted-foreground">{s('last_day')}: </span>{formatDate(separation.last_day, locale)}</div>
          {separation.deemed_accept_on && waiting && <div className="font-medium text-amber-700"><span>{s('deemed_accept_on')}: </span>{formatDate(separation.deemed_accept_on, locale)}</div>}
        </div>
        {separation.deemed && <p className="text-xs text-muted-foreground">{s('deemed')}</p>}
        {separation.source === 'employee' && <p className="text-xs text-muted-foreground">{s('by_employee')}</p>}
        {separation.reason && <p className="rounded bg-muted p-2">{separation.reason}</p>}
        {separation.checklist.length > 0 && (
          <div>
            <div className="mb-1 font-medium">{s('checklist')}</div>
            <ul className="grid gap-1 sm:grid-cols-2">
              {separation.checklist.map((item) => (
                <li key={item.key} className="flex items-start gap-2">
                  {item.auto
                    ? <span className={cn('mt-0.5 flex size-4 items-center justify-center rounded-sm border', item.done && 'border-emerald-600 bg-emerald-600 text-white')}>{item.done && <Check className="size-3" />}</span>
                    : <Checkbox className="mt-0.5" checked={item.done !== null} disabled={!separation.can_decide} onCheckedChange={(checked) => act('check', { item: item.key, done: checked === true })} />}
                  <span>
                    {s(`items.${item.key}`)}
                    {item.blockers?.map((blocker) => <span key={blocker} className="block text-xs text-destructive">{blocker}</span>)}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        )}
        {separation.can_decide && (
          <div className="flex flex-wrap items-end gap-2">
            {waiting && <Button size="sm" onClick={() => act('accept')}>{s('accept')}</Button>}
            {separation.status === 'submitted' && separation.kind === 'resignation' && (
              <span className="flex items-end gap-1">
                <FormField label={s('defer_to', { max: formatDate(maxDefer, locale) })}><Input type="date" dir="ltr" max={maxDefer} value={deferTo} onChange={(event) => setDeferTo(event.target.value)} /></FormField>
                <Button size="sm" variant="outline" disabled={deferTo === ''} onClick={() => act('defer', { last_day: deferTo })}>{s('defer')}</Button>
              </span>
            )}
            {separation.status === 'accepted' && <Button size="sm" variant="destructive" onClick={() => act('complete')}>{s('complete')}</Button>}
            <Button size="sm" variant="ghost" onClick={() => act('cancel')}>{s('cancel')}</Button>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

/** Who is leaving, when a resignation counts as accepted, and the exit checklist. */
export default function SeparationsIndex({ separations, view, max_defer_days: maxDeferDays, kinds, employees }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const s = (key: string) => t(`hr.separations.${key}`);

  return (
    <SuiteLayout title={s('title')}>
      <PageHeader
        title={s('title')}
        description={s('lede')}
        actions={<>
          <Button variant={view === 'open' ? 'secondary' : 'ghost'} asChild><Link href={url('/hr/separations')}>{s('open')}</Link></Button>
          <Button variant={view === 'history' ? 'secondary' : 'ghost'} asChild><Link href={url('/hr/separations?view=history')}>{s('history')}</Link></Button>
          {employees.length > 0 && <NewSeparation employees={employees} kinds={kinds} />}
        </>}
      />
      {separations.length === 0 && <p className="text-sm text-muted-foreground">{s('none')}</p>}
      {view === 'open'
        ? <div className="grid gap-3 lg:grid-cols-2">{separations.map((separation) => <SeparationCard key={separation.id} separation={separation} maxDeferDays={maxDeferDays} />)}</div>
        : (
          <Card><CardContent className="divide-y">
            {separations.map((separation) => (
              <div key={separation.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <Link href={url(`/hr/employees/${separation.employee_id}`)} className="font-medium hover:underline">{separation.employee}</Link>
                <span className="flex items-center gap-2"><Badge variant="outline">{s(`kinds.${separation.kind}`)}</Badge>{s(`statuses.${separation.status}`)} · {formatDate(separation.last_day, locale)}</span>
              </div>
            ))}
          </CardContent></Card>
        )}
    </SuiteLayout>
  );
}
