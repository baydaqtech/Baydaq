import { Link } from '@inertiajs/react';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { Button } from '@/components/ui/button';

interface Props {
  by_branch: { branch: string; total: number }[];
  by_section: { section: string; total: number }[];
  total: number;
}

function Bars({ rows, label }: { rows: { label: string; total: number }[]; label: string }) {
  const { locale } = useLocale();
  const max = Math.max(1, ...rows.map((row) => row.total));

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{label}</CardTitle></CardHeader>
      <CardContent>
        <ul className="flex flex-col gap-2">
          {rows.map((row) => (
            <li key={row.label} className="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
              <div>
                <div className="mb-1 flex justify-between"><span>{row.label}</span><span className="text-muted-foreground">{formatNumber(row.total, locale)}</span></div>
                <div className="h-2 rounded-full bg-muted"><div className="h-2 rounded-full bg-brass" style={{ width: `${(row.total / max) * 100}%` }} /></div>
              </div>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

export default function Headcount({ by_branch: byBranch, by_section: bySection, total }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();

  return (
    <SuiteLayout title={t('hr.reports.headcount')}>
      <PageHeader title={t('hr.reports.headcount')} actions={can('hr.payroll.view_eosb') && <Button asChild size="sm" variant="outline"><Link href={url('/hr/reports/eosb')}>{t('hr.reports.eosb')}</Link></Button>} />
      <div className="mb-4 max-w-xs"><StatCard label={t('hr.reports.total')} value={formatNumber(total, locale)} /></div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Bars label={t('hr.reports.by_branch')} rows={byBranch.map((row) => ({ label: row.branch, total: row.total }))} />
        <Bars label={t('hr.reports.by_section')} rows={bySection.map((row) => ({ label: row.section, total: row.total }))} />
      </div>
    </SuiteLayout>
  );
}
