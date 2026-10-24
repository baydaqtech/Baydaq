import { useState } from 'react';
import LeaveBalanceCard, { type LeaveBalance } from '@/components/hr/LeaveBalanceCard';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { EmployeeCan } from '@/types/hr';
import type { Option } from '@/types/resources';

export interface LeaveItem { id: number; employee_id: number; type: string; is_paid: boolean; starts_on: string; ends_on: string; days: string; status: string; reason: string | null; decision_note: string | null }
export interface LeaveData { requests: LeaveItem[]; balances: LeaveBalance[]; types: Option[] }

export function LeaveRequestDialog({ employeeId, types }: { employeeId: number; types: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ leave_type_id: types[0]?.value ?? '', starts_on: '', ends_on: '', reason: '' });
  const f = (field: string) => t(`hr.leave.fields.${field}`);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline"><Plus data-icon="inline-start" />{t('hr.leave.new')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employeeId}/leave`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('hr.leave.new')}</DialogTitle></DialogHeader>
          <FormField label={f('type')} error={form.errors.leave_type_id}><SelectInput value={form.data.leave_type_id} onChange={(value) => form.setData('leave_type_id', Number(value))} options={types} /></FormField>
          <FormField label={f('starts_on')} error={form.errors.starts_on}><Input type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
          <FormField label={f('ends_on')} error={form.errors.ends_on ?? (form.errors as Record<string, string>).days}><Input type="date" dir="ltr" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} /></FormField>
          <FormField label={f('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** Opening balances from the old system, corrections and granted days (HR with leave approval). */
function AdjustBalanceDialog({ employeeId, balances }: { employeeId: number; balances: LeaveBalance[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ leave_type_id: balances[0]?.type_id ?? '', days: '', effective_on: new Date().toISOString().slice(0, 10), reason: '' });
  const f = (field: string) => t(`hr.leave.adjust.${field}`);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="ghost">{t('hr.leave.adjust.title')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employeeId}/leave-adjustments`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('hr.leave.adjust.title')}</DialogTitle></DialogHeader>
          <FormField label={t('hr.leave.fields.type')} error={form.errors.leave_type_id}><SelectInput value={form.data.leave_type_id} onChange={(value) => form.setData('leave_type_id', Number(value))} options={balances.map((balance) => ({ value: balance.type_id, label: balance.type }))} /></FormField>
          <FormField label={f('days')} hint={f('days_hint')} error={form.errors.days}><Input dir="ltr" inputMode="decimal" value={form.data.days} onChange={(event) => form.setData('days', event.target.value)} /></FormField>
          <FormField label={f('effective_on')} error={form.errors.effective_on}><Input dir="ltr" type="date" value={form.data.effective_on} onChange={(event) => form.setData('effective_on', event.target.value)} /></FormField>
          <FormField label={f('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function LeaveDecision({ leave }: { leave: LeaveItem }) {
  const t = useT();
  const url = useSuiteUrl();

  return (
    <span className="inline-flex gap-1">
      {leave.status === 'pending' && (
        <>
          <ConfirmAction href={url(`/hr/leave/${leave.id}`)} method="put" data={{ status: 'approved' }} size="xs" title={t('hr.leave.approve')}>{t('hr.leave.approve')}</ConfirmAction>
          <ConfirmAction href={url(`/hr/leave/${leave.id}`)} method="put" data={{ status: 'rejected' }} size="xs" variant="ghost" title={t('hr.leave.reject')}>{t('hr.leave.reject')}</ConfirmAction>
        </>
      )}
      {leave.status === 'approved' && <ConfirmAction href={url(`/hr/leave/${leave.id}`)} method="put" data={{ status: 'cancelled' }} size="xs" variant="ghost" title={t('hr.leave.cancel')}>{t('hr.leave.cancel')}</ConfirmAction>}
    </span>
  );
}

export default function LeaveTab({ employeeId, leave, can }: { employeeId: number; leave: LeaveData; can: EmployeeCan }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {leave.balances.map((balance) => <LeaveBalanceCard key={balance.type_id} balance={balance} />)}
      </div>
      {can.approve_leave && leave.balances.length > 0 && <div><AdjustBalanceDialog employeeId={employeeId} balances={leave.balances} /></div>}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between"><CardTitle className="text-base">{t('hr.leave.title')}</CardTitle>{can.manage_leave && <LeaveRequestDialog employeeId={employeeId} types={leave.types} />}</CardHeader>
        <CardContent>
          <ul className="divide-y text-sm">
            {leave.requests.length === 0 && <li className="py-3 text-muted-foreground">{t('hr.leave.none')}</li>}
            {leave.requests.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span><span className="font-medium">{item.type}</span> <span className="text-muted-foreground">· {formatDate(item.starts_on, locale)} → {formatDate(item.ends_on, locale)} · {item.days} {t('hr.leave.fields.days').toLowerCase()}</span></span>
                <span className="flex items-center gap-2"><StatusBadge group="leave_status" value={item.status} file="hr" />{can.approve_leave && <LeaveDecision leave={item} />}</span>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>
    </div>
  );
}
