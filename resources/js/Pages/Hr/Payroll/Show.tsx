import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, RefreshCw } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

interface DiffRow { employee_id: number; name: string; net: string }

export interface PayrollDiffData {
  previous_month: string;
  net_change: string;
  joined: DiffRow[];
  left: DiffRow[];
  changed: { employee_id: number; name: string; from: string; to: string; change: string; parts: Record<string, string> }[];
}

const PART_LABELS: Record<string, string> = { basic: 'basic', allowances: 'allowances', bonuses: 'bonuses', deductions: 'deductions', advance_installments: 'advances', unpaid_leave: 'unpaid_leave', gosi_employee: 'gosi' };

/** Joiners, leavers and moved nets against the previous month, shown before approval (H11). */
function WhatChanged({ diff }: { diff: PayrollDiffData | null }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const money = (value: string) => formatMoney(value, locale);
  const signed = (value: string) => `${Number(value) > 0 ? '+' : ''}${money(value)}`;

  if (diff === null) {
    return <Card className="mb-4"><CardContent className="py-4 text-sm text-muted-foreground">{t('hr.payroll.diff.first')}</CardContent></Card>;
  }

  const empty = diff.joined.length === 0 && diff.left.length === 0 && diff.changed.length === 0;

  return (
    <Card className="mb-4">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle className="text-base">{t('hr.payroll.diff.title', { month: diff.previous_month })}</CardTitle>
        <span className="text-sm text-muted-foreground">{t('hr.payroll.diff.net_change')}: <strong dir="ltr">{signed(diff.net_change)}</strong></span>
      </CardHeader>
      <CardContent className="flex flex-col gap-4 text-sm">
        {empty && <p className="text-muted-foreground">{t('hr.payroll.diff.nothing')}</p>}
        {[['joined', diff.joined], ['left', diff.left]].map(([key, rows]) => (rows as DiffRow[]).length > 0 && (
          <div key={key as string}>
            <h3 className="mb-1 font-medium">{t(`hr.payroll.diff.${key}`)} ({(rows as DiffRow[]).length})</h3>
            <ul className="flex flex-wrap gap-2">{(rows as DiffRow[]).map((row) => <li key={row.employee_id}><Badge variant="outline"><Link href={url(`/hr/employees/${row.employee_id}`)}>{row.name}</Link> · {money(row.net)}</Badge></li>)}</ul>
          </div>
        ))}
        {diff.changed.length > 0 && (
          <div>
            <h3 className="mb-1 font-medium">{t('hr.payroll.diff.changed')} ({diff.changed.length})</h3>
            <ul className="divide-y">
              {diff.changed.map((row) => (
                <li key={row.employee_id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <Link href={url(`/hr/employees/${row.employee_id}`)} className="font-medium hover:underline">{row.name}</Link>
                  <span className="flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                    {Object.entries(row.parts).map(([part, change]) => <Badge key={part} variant="outline">{t(`hr.payroll.fields.${PART_LABELS[part] ?? part}`)} <span dir="ltr">{signed(change)}</span></Badge>)}
                  </span>
                  <span dir="ltr">{money(row.from)} → <strong>{money(row.to)}</strong> ({signed(row.change)})</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default function PayrollShow({ run, lines, diff, can }: { run: RunRow; lines: Line[]; diff: PayrollDiffData | null; can: { approve: boolean; rebuild: boolean; mark_paid: boolean; export: boolean } }) {
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
      {run.status === 'draft' && <WhatChanged diff={diff} />}
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
