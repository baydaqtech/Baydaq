import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, RefreshCw } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { RunRow } from './Index';

interface Line {
  id: number; employee_id: number; employee: string; employee_number: string; branch: string;
  basic: string; allowances: string; bonuses: string; deductions: string; advance_installments: string; unpaid_leave: string;
  gosi_employee: string; gosi_employer: string; gross: string; net: string;
  breakdown: { kind: string; label_ar: string; label_en: string; amount: string }[];
  [key: string]: unknown;
}

function PaidDialog({ run }: { run: RunRow }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ paid_on: new Date().toISOString().slice(0, 10) });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><CheckCircle2 data-icon="inline-start" />{t('hr.payroll.mark_paid')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/payroll/${run.id}/paid`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('hr.payroll.mark_paid')}</DialogTitle></DialogHeader>
          <FormField label={t('hr.payroll.fields.paid_on')} error={form.errors.paid_on}><Input type="date" dir="ltr" value={form.data.paid_on} onChange={(event) => form.setData('paid_on', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function PayrollShow({ run, lines, can }: { run: RunRow; lines: Line[]; can: { approve: boolean; rebuild: boolean; mark_paid: boolean; export: boolean } }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const f = (field: string) => t(`hr.payroll.fields.${field}`);
  const money = (value: string) => formatMoney(value, locale);

  return (
    <SuiteLayout title={`${t('hr.payroll.title')} ${run.pay_month}`}>
      <PageHeader
        title={<span className="flex items-center gap-2"><span dir="ltr">{run.pay_month}</span> · {run.company} <StatusBadge group="payroll_status" value={run.status} file="hr" /></span>}
        actions={(
          <>
            {can.rebuild && <ConfirmAction href={url('/hr/payroll')} method="post" data={{ company_id: lines[0] ? undefined : undefined, pay_month: `${run.pay_month}-01` }} size="sm" variant="outline" title={t('hr.payroll.rebuild')}><RefreshCw data-icon="inline-start" />{t('hr.payroll.rebuild')}</ConfirmAction>}
            {can.approve && <ConfirmAction href={url(`/hr/payroll/${run.id}/approve`)} method="put" size="sm" title={t('hr.payroll.approve')} description={`${formatNumber(run.employees_count, locale)} · ${money(run.total_net)}`}>{t('hr.payroll.approve')}</ConfirmAction>}
            {can.mark_paid && <PaidDialog run={run} />}
            {can.export && <Button asChild size="sm" variant="outline"><a href={url(`/hr/payroll/${run.id}/wps`)}><Download data-icon="inline-start" />{t('hr.payroll.wps')}</a></Button>}
          </>
        )}
      />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={f('employees')} value={formatNumber(run.employees_count, locale)} />
        <StatCard label={f('gross')} value={money(run.total_gross)} />
        <StatCard label={f('deductions')} value={money(run.total_deductions)} />
        <StatCard label={f('net')} value={money(run.total_net)} />
      </div>
      <DataTable<Line>
        rows={lines}
        columns={[
          { key: 'employee', label: f('employees'), render: (line) => <Link href={url(`/hr/employees/${line.employee_id}`)} className="font-medium hover:underline">{line.employee}<span className="block text-xs text-muted-foreground">{line.branch}</span></Link> },
          { key: 'basic', label: f('basic'), render: (line) => money(line.basic) },
          { key: 'allowances', label: f('allowances'), render: (line) => money(line.allowances) },
          { key: 'bonuses', label: f('bonuses'), render: (line) => money(line.bonuses) },
          { key: 'deductions', label: f('deductions'), render: (line) => money(line.deductions) },
          { key: 'advance_installments', label: f('advances'), render: (line) => money(line.advance_installments) },
          { key: 'unpaid_leave', label: f('unpaid_leave'), render: (line) => money(line.unpaid_leave) },
          { key: 'gosi_employee', label: f('gosi'), render: (line) => money(line.gosi_employee) },
          { key: 'net', label: f('net'), render: (line) => <strong>{money(line.net)}</strong> },
        ]}
      />
      <p className="mt-3 text-xs text-muted-foreground">{f('employer_gosi')}: {money(run.total_employer_gosi)}</p>
    </SuiteLayout>
  );
}
