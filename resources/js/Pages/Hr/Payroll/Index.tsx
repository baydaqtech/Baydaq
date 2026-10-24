import { useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface RunRow { id: number; company: string | null; pay_month: string; status: string; employees_count: number; total_gross: string; total_deductions: string; total_net: string; total_employer_gosi: string; paid_on: string | null; [key: string]: unknown }

export default function PayrollIndex({ runs, companies }: { runs: RunRow[]; companies: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const form = useForm({ company_id: companies[0]?.value ?? '', pay_month: new Date().toISOString().slice(0, 7) + '-01' });
  const f = (field: string) => t(`hr.payroll.fields.${field}`);

  return (
    <SuiteLayout title={t('hr.payroll.title')}>
      <PageHeader title={t('hr.payroll.runs')} />
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
