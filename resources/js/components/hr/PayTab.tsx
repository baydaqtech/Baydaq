import { useState } from 'react';
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
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuite, useSuiteUrl } from '@/lib/suite';
import type { EmployeeCan } from '@/types/hr';
import type { Option } from '@/types/resources';

interface Adjustment { id: number; type: string; amount: string; pay_month: string; status: string; reason: string | null; origin?: string }
interface Advance {
  id: number; kind: string; amount: string; installments: number; status: string; balance: string; first_installment_month: string; reason: string | null;
  schedule: { due_month: string; amount: string; paid: string; status: string }[];
  repayments: { paid_on: string; amount: string; method: string }[];
}
export interface PayData { bonuses: Adjustment[]; deductions: Adjustment[]; advances: Advance[]; bonus_types: Option[]; deduction_types: Option[] }

const thisMonth = () => new Date().toISOString().slice(0, 7) + '-01';

function AdjustmentDialog({ kind, employeeId, types }: { kind: 'bonuses' | 'deductions'; employeeId: number; types: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const typeField = kind === 'bonuses' ? 'bonus_type_id' : 'deduction_type_id';
  const form = useForm<Record<string, string | number>>({ [typeField]: types[0]?.value ?? '', amount: '', pay_month: thisMonth(), reason: '' });
  const f = (field: string) => t(`hr.pay.fields.${field}`);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline"><Plus data-icon="inline-start" />{t(kind === 'bonuses' ? 'hr.pay.new_bonus' : 'hr.pay.new_deduction')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employeeId}/${kind}`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t(kind === 'bonuses' ? 'hr.pay.new_bonus' : 'hr.pay.new_deduction')}</DialogTitle></DialogHeader>
          <FormField label={f('type')} error={form.errors[typeField]}><SelectInput value={form.data[typeField]} onChange={(value) => form.setData(typeField, Number(value))} options={types} /></FormField>
          <FormField label={f('amount')} error={form.errors.amount}><Input type="number" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
          <FormField label={f('pay_month')} error={form.errors.pay_month}><Input type="date" dir="ltr" value={String(form.data.pay_month)} onChange={(event) => form.setData('pay_month', event.target.value)} /></FormField>
          <FormField label={f('reason')} error={form.errors.reason}><Input value={String(form.data.reason)} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function AdvanceDialog({ employeeId }: { employeeId: number }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ kind: 'advance', amount: '', installments: 3, first_installment_month: thisMonth(), reason: '' });
  const f = (field: string) => t(`hr.pay.fields.${field}`);
  const { features } = useSuite();

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline"><Plus data-icon="inline-start" />{t('hr.pay.new_advance')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employeeId}/advances`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('hr.pay.new_advance')}</DialogTitle></DialogHeader>
          {features.includes('hr.loans') && <FormField label={t('hr.me.loan')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={['advance', 'loan'].map((kind) => ({ value: kind, label: t(`hr.me.kinds.${kind}`) }))} /></FormField>}
          <FormField label={f('amount')} error={form.errors.amount}><Input type="number" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
          <FormField label={f('installments')} error={form.errors.installments}><Input type="number" min={1} max={120} dir="ltr" value={form.data.installments} onChange={(event) => form.setData('installments', Number(event.target.value))} /></FormField>
          <FormField label={f('first_installment_month')} error={form.errors.first_installment_month}><Input type="date" dir="ltr" value={form.data.first_installment_month} onChange={(event) => form.setData('first_installment_month', event.target.value)} /></FormField>
          <FormField label={f('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function RepayDialog({ employeeId, advance }: { employeeId: number; advance: Advance }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ action: 'repay', amount: '', method: 'cash', reference: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline">{t('hr.pay.repay')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/employees/${employeeId}/advances/${advance.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('hr.pay.repay')} · {t('hr.pay.balance')} {advance.balance}</DialogTitle></DialogHeader>
          <FormField label={t('hr.pay.fields.amount')} error={form.errors.amount}><Input type="number" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
          <FormField label={t('hr.pay.fields.method')} error={form.errors.method}><SelectInput value={form.data.method} onChange={(value) => form.setData('method', value)} options={['cash', 'transfer'].map((method) => ({ value: method, label: t(`hr.repayment_method.${method}`) }))} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function PayTab({ employeeId, pay, can }: { employeeId: number; pay: PayData; can: EmployeeCan }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const decide = (kind: 'bonuses' | 'deductions', item: Adjustment) => item.status === 'pending' && can.approve_pay && (
    <span className="inline-flex gap-1">
      <ConfirmAction href={url(`/hr/employees/${employeeId}/pay/${kind}/${item.id}`)} method="put" data={{ approve: true }} size="xs" title={t('hr.pay.approve')}>{t('hr.pay.approve')}</ConfirmAction>
      <ConfirmAction href={url(`/hr/employees/${employeeId}/pay/${kind}/${item.id}`)} method="put" data={{ approve: false }} size="xs" variant="ghost" title={t('hr.pay.reject')}>{t('hr.pay.reject')}</ConfirmAction>
    </span>
  );

  const list = (kind: 'bonuses' | 'deductions', items: Adjustment[], types: Option[]) => (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between"><CardTitle className="text-base">{t(`hr.pay.${kind}`)}</CardTitle>{can.manage_pay && <AdjustmentDialog kind={kind} employeeId={employeeId} types={types} />}</CardHeader>
      <CardContent>
        <ul className="divide-y text-sm">
          {items.length === 0 && <li className="py-3 text-muted-foreground">—</li>}
          {items.map((item) => (
            <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span><span className="font-medium">{item.type}</span> <span className="text-muted-foreground">· {item.pay_month}{item.reason && ` · ${item.reason}`}</span></span>
              <span className="flex items-center gap-2">{formatMoney(item.amount, locale)}<StatusBadge group="adjustment_status" value={item.status} file="hr" />{decide(kind, item)}</span>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      {list('bonuses', pay.bonuses, pay.bonus_types)}
      {list('deductions', pay.deductions, pay.deduction_types)}
      <Card className="lg:col-span-2">
        <CardHeader className="flex flex-row items-center justify-between"><CardTitle className="text-base">{t('hr.pay.advances')}</CardTitle>{can.manage_pay && <AdvanceDialog employeeId={employeeId} />}</CardHeader>
        <CardContent className="flex flex-col gap-3">
          {pay.advances.length === 0 && <p className="text-sm text-muted-foreground">—</p>}
          {pay.advances.map((advance) => (
            <div key={advance.id} className="rounded-lg border p-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="flex items-center gap-2 font-medium">{advance.kind === 'loan' && t('hr.me.kinds.loan')} {formatMoney(advance.amount, locale)} · {advance.installments} × <StatusBadge group="advance_status" value={advance.status} file="hr" /></span>
                <span className="flex items-center gap-1.5">
                  <span className="text-muted-foreground">{t('hr.pay.balance')}: {formatMoney(advance.balance, locale)}</span>
                  {advance.status === 'pending' && can.approve_pay && (
                    <>
                      <ConfirmAction href={url(`/hr/employees/${employeeId}/advances/${advance.id}`)} method="put" data={{ action: 'approve' }} size="xs" title={t('hr.pay.approve')}>{t('hr.pay.approve')}</ConfirmAction>
                      <ConfirmAction href={url(`/hr/employees/${employeeId}/advances/${advance.id}`)} method="put" data={{ action: 'reject' }} size="xs" variant="ghost" title={t('hr.pay.reject')}>{t('hr.pay.reject')}</ConfirmAction>
                    </>
                  )}
                  {advance.status === 'approved' && can.manage_pay && <ConfirmAction href={url(`/hr/employees/${employeeId}/advances/${advance.id}`)} method="put" data={{ action: 'disburse', method: 'transfer' }} size="xs" title={t('hr.pay.disburse')}>{t('hr.pay.disburse')}</ConfirmAction>}
                  {advance.status === 'disbursed' && can.manage_pay && (
                    <>
                      <RepayDialog employeeId={employeeId} advance={advance} />
                      <ConfirmAction href={url(`/hr/employees/${employeeId}/advances/${advance.id}`)} method="put" data={{ action: 'settle' }} size="xs" variant="ghost" title={t('hr.pay.settle')}>{t('hr.pay.settle')}</ConfirmAction>
                    </>
                  )}
                </span>
              </div>
              <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
                {advance.schedule.map((line) => (
                  <span key={line.due_month} className={`rounded-full border px-2 py-0.5 ${line.status === 'paid' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : line.status === 'skipped' ? 'text-muted-foreground line-through' : ''}`} dir="ltr">{line.due_month}: {line.paid}/{line.amount}</span>
                ))}
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
