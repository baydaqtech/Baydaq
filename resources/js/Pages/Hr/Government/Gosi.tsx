import { Link } from '@inertiajs/react';
import { ExternalLink, Info } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface Row {
  id: number; name: string; number: string; company: string | null; saudi: boolean; scheme: 'legacy' | 'new' | null; pension_rate: string | null;
  registered_on: string | null; wage: string; employee_share: string; employer_share: string; [key: string]: unknown;
}

interface Props {
  rows: Row[];
  totals: { employee: string; employer: string };
  new_rate: string;
  legacy_rate: string;
  new_system_from: string;
  unknown: number;
  portal: string;
}

/** GOSI this month under the 2024 law: legacy and new-law Saudis side by side. */
export default function Gosi({ rows, totals, new_rate: newRate, legacy_rate: legacyRate, new_system_from: newSystemFrom, unknown, portal }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const g = (key: string, replace?: Record<string, string>) => t(`hr.gov.gosi.${key}`, replace);
  const percent = (rate: string) => formatPercent(Number(rate) * 100, locale);

  return (
    <SuiteLayout title={g('title')}>
      <PageHeader
        title={g('title')}
        description={g('lede', { date: formatDate(newSystemFrom, locale), new: percent(newRate), legacy: percent(legacyRate) })}
        actions={<Button asChild variant="outline"><a href={portal} target="_blank" rel="noreferrer"><ExternalLink />{g('open_portal')}</a></Button>}
      />
      {unknown > 0 && (
        <Card className="mb-4 border-amber-400">
          <CardContent className="flex items-start gap-2 text-sm"><Info className="mt-0.5 size-4 shrink-0 text-amber-600" />{g('unknown', { count: String(unknown) })}</CardContent>
        </Card>
      )}
      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <Card><CardContent><div className="text-sm text-muted-foreground">{g('employee_share')}</div><div className="text-2xl font-semibold">{formatMoney(totals.employee, locale)}</div></CardContent></Card>
        <Card><CardContent><div className="text-sm text-muted-foreground">{g('employer_share')}</div><div className="text-2xl font-semibold">{formatMoney(totals.employer, locale)}</div></CardContent></Card>
      </div>
      <DataTable<Row>
        rows={rows}
        columns={[
          { key: 'name', label: g('employee'), render: (row) => <Link href={url(`/hr/employees/${row.id}`)} className="font-medium hover:underline">{row.name} <span className="text-xs text-muted-foreground" dir="ltr">{row.number}</span></Link> },
          { key: 'company', label: g('company') },
          { key: 'scheme', label: g('scheme'), render: (row) => (row.scheme ? <Badge variant={row.scheme === 'new' ? 'default' : 'outline'}>{g(`schemes.${row.scheme}`)}</Badge> : <span className="text-xs text-muted-foreground">{g('non_saudi')}</span>) },
          { key: 'pension_rate', label: g('pension_rate'), render: (row) => (row.pension_rate ? percent(row.pension_rate) : '—') },
          { key: 'wage', label: g('wage'), render: (row) => formatMoney(row.wage, locale) },
          { key: 'employee_share', label: g('employee_share'), render: (row) => formatMoney(row.employee_share, locale) },
          { key: 'employer_share', label: g('employer_share'), render: (row) => formatMoney(row.employer_share, locale) },
        ]}
      />
    </SuiteLayout>
  );
}
