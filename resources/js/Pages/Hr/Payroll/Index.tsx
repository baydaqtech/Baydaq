import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Gift, Play } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface RunRow { id: number; company: string | null; pay_month: string; status: string; employees_count: number; total_gross: string; total_deductions: string; total_net: string; total_employer_gosi: string; paid_on: string | null; [key: string]: unknown }

/** One bonus for every active employee in reach, e.g. for Eid (H9). */
function BonusBatchDialog({ bonusTypes, companies }: { bonusTypes: Option[]; companies: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ bonus_type_id: String(bonusTypes[0]?.value ?? ''), calculation: 'fixed', value: '', pay_month: new Date().toISOString().slice(0, 7) + '-01', reason: '', company_id: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><Gift data-icon="inline-start" />{t('hr.pay.batch')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ ...data, company_id: data.company_id || null })); form.post(url('/hr/bonus-batch'), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="grid gap-3 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('hr.pay.batch')}</DialogTitle><DialogDescription>{t('hr.pay.batch_hint')}</DialogDescription></DialogHeader>
          <FormField label={t('hr.pay.fields.type')} error={form.errors.bonus_type_id}><SelectInput value={form.data.bonus_type_id} onChange={(value) => form.setData('bonus_type_id', value)} options={bonusTypes} /></FormField>
          <FormField label={t('hr.payroll.fields.company')}><SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', value)} options={companies} emptyLabel={t('common.actions.all')} /></FormField>
          <FormField label={t('hr.catalog.fields.calculation')}><SelectInput value={form.data.calculation} onChange={(value) => form.setData('calculation', value)} options={[{ value: 'fixed', label: t('hr.allowance_calculation.fixed') }, { value: 'percent_of_basic', label: t('hr.allowance_calculation.percent_of_basic') }]} /></FormField>
          <FormField label={t('hr.pay.fields.amount')} error={form.errors.value}><Input type="number" step="0.01" min="0" dir="ltr" value={form.data.value} onChange={(event) => form.setData('value', event.target.value)} /></FormField>
          <FormField label={t('hr.pay.fields.pay_month')} error={form.errors.pay_month}><Input type="date" dir="ltr" value={form.data.pay_month} onChange={(event) => form.setData('pay_month', event.target.value)} /></FormField>
          <FormField label={t('hr.pay.fields.reason')}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function PayrollIndex({ runs, companies, bonus_types: bonusTypes }: { runs: RunRow[]; companies: Option[]; bonus_types: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const form = useForm({ company_id: companies[0]?.value ?? '', pay_month: new Date().toISOString().slice(0, 7) + '-01' });
  const f = (field: string) => t(`hr.payroll.fields.${field}`);

  return (
    <SuiteLayout title={t('hr.payroll.title')}>
      <PageHeader title={t('hr.payroll.runs')} actions={can('hr.pay.create') && bonusTypes.length > 0 && <BonusBatchDialog bonusTypes={bonusTypes} companies={companies} />} />
      {companies.length > 0 && (
        <Card className="mb-4"><CardContent>
          <form onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/payroll')); }} className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <FormField label={f('company')} error={form.errors.company_id}><SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>
            <FormField label={f('pay_month')} error={form.errors.pay_month}><Input type="date" dir="ltr" value={form.data.pay_month} onChange={(event) => form.setData('pay_month', event.target.value)} /></FormField>
            <Button type="submit" disabled={form.processing}><Play data-icon="inline-start" />{t('hr.payroll.build')}</Button>
          </form>
        </CardContent></Card>
      )}
      <DataTable<RunRow>
        rows={runs}
        empty={t('hr.payroll.none')}
        rowHref={(run) => url(`/hr/payroll/${run.id}`)}
        columns={[
          { key: 'pay_month', label: f('pay_month'), render: (run) => <span className="font-mono" dir="ltr">{run.pay_month}</span> },
          { key: 'company', label: f('company') },
          { key: 'employees_count', label: f('employees'), render: (run) => formatNumber(run.employees_count, locale) },
          { key: 'total_gross', label: f('gross'), render: (run) => formatMoney(run.total_gross, locale) },
          { key: 'total_net', label: f('net'), render: (run) => <strong>{formatMoney(run.total_net, locale)}</strong> },
          { key: 'status', label: f('status'), render: (run) => <StatusBadge group="payroll_status" value={run.status} file="hr" /> },
        ]}
      />
    </SuiteLayout>
  );
}
