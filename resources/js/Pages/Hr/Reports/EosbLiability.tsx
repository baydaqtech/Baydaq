import { Link } from '@inertiajs/react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface EmployeeRow { id: number; name: string; company: string; years: number; monthly_wage: string; award: string; [key: string]: unknown }

interface Props {
  total: string;
  companies: { company_id: number; company: string; total: string; employees: number }[];
  employees: EmployeeRow[];
}

/** End-of-service liability today, per company and per person (H10). */
export default function EosbLiability({ total, companies, employees }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const money = (value: string) => formatMoney(value, locale);

  return (
    <SuiteLayout title={t('hr.reports.eosb')}>
      <PageHeader title={t('hr.reports.eosb')} description={t('hr.reports.eosb_hint')} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t('hr.reports.total')} value={money(total)} />
        <StatCard label={t('hr.payroll.fields.employees')} value={formatNumber(employees.length, locale)} />
      </div>
      {companies.length > 1 && (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{t('hr.reports.by_company')}</CardTitle></CardHeader>
          <CardContent>
            <ul className="divide-y text-sm">
              {companies.map((row) => <li key={row.company_id} className="flex justify-between py-2"><span>{row.company} · {formatNumber(row.employees, locale)}</span><strong>{money(row.total)}</strong></li>)}
            </ul>
          </CardContent>
        </Card>
      )}
      <DataTable<EmployeeRow>
        rows={employees}
        columns={[
          { key: 'name', label: t('hr.reports.by_employee'), render: (row) => <Link href={url(`/hr/employees/${row.id}`)} className="font-medium hover:underline">{row.name}<span className="block text-xs text-muted-foreground">{row.company}</span></Link> },
          { key: 'years', label: t('hr.payroll.years'), render: (row) => formatNumber(row.years, locale) },
          { key: 'monthly_wage', label: t('hr.payroll.monthly_wage'), render: (row) => money(row.monthly_wage) },
          { key: 'award', label: t('hr.payroll.award'), render: (row) => <strong>{money(row.award)}</strong> },
        ]}
      />
    </SuiteLayout>
  );
}
