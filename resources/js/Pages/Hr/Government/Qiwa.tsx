import { Link, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';

interface ContractRow {
  id: number; employee_id: number; employee: string; number: string; company: string | null; starts_on: string;
  qiwa_status: string; qiwa_reference: string | null; qiwa_documented_on: string | null; can_update: boolean; [key: string]: unknown;
}

interface Props {
  summary: { company: string | null; total: number; documented: number }[];
  contracts: ContractRow[];
  statuses: string[];
  filters: Filters;
  portal: string;
}

const TONE: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = { documented: 'default', sent: 'secondary', rejected: 'destructive', not_started: 'outline' };

function StatusEditor({ contract, statuses }: { contract: ContractRow; statuses: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const form = useForm({ qiwa_status: contract.qiwa_status, qiwa_reference: contract.qiwa_reference ?? '' });

  return (
    <form className="flex items-center gap-2" onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/gov/qiwa/${contract.id}`), { preserveScroll: true }); }}>
      <SelectInput className="w-48" value={form.data.qiwa_status} onChange={(value) => form.setData('qiwa_status', value)} options={statuses.map((value) => ({ value, label: t(`hr.gov.qiwa.statuses.${value}`) }))} />
      <Input className="w-36" dir="ltr" placeholder={t('hr.gov.qiwa.reference')} value={form.data.qiwa_reference} onChange={(event) => form.setData('qiwa_reference', event.target.value)} />
      <Button size="sm" type="submit" variant="outline" disabled={form.processing || !form.isDirty}>{t('hr.gov.muqeem.save')}</Button>
    </form>
  );
}

/** Which active contracts are documented on Qiwa, company by company. */
export default function Qiwa({ summary, contracts, statuses, filters: initialFilters, portal }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const q = (key: string, replace?: Record<string, string | number>) => t(`hr.gov.qiwa.${key}`, replace as Record<string, string>);

  return (
    <SuiteLayout title={q('title')}>
      <PageHeader title={q('title')} description={q('lede')} actions={<Button asChild variant="outline"><a href={portal} target="_blank" rel="noreferrer"><ExternalLink />{q('open_portal')}</a></Button>} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {summary.map((row) => {
          const percent = row.total === 0 ? 0 : (row.documented / row.total) * 100;

          return (
            <Card key={row.company ?? ''}>
              <CardContent className="flex flex-col gap-2">
                <div className="flex items-center justify-between text-sm"><span className="font-medium">{row.company}</span><span className="text-muted-foreground">{q('of', { done: String(row.documented), total: String(row.total) })}</span></div>
                <div className="text-2xl font-semibold">{formatPercent(percent, locale)}</div>
                <Progress value={percent} />
              </CardContent>
            </Card>
          );
        })}
      </div>
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: q('status'), options: statuses.map((value) => ({ value, label: q(`statuses.${value}`) })) }]} />
      <DataTable<ContractRow>
        rows={contracts}
        empty={q('none')}
        columns={[
          { key: 'employee', label: q('employee'), render: (row) => <Link href={url(`/hr/employees/${row.employee_id}`)} className="font-medium hover:underline">{row.employee} <span className="text-xs text-muted-foreground" dir="ltr">{row.number}</span></Link> },
          { key: 'company', label: q('company') },
          { key: 'starts_on', label: q('starts_on'), render: (row) => formatDate(row.starts_on, locale) },
          { key: 'qiwa_status', label: q('status'), render: (row) => row.can_update ? <StatusEditor contract={row} statuses={statuses} /> : <Badge variant={TONE[row.qiwa_status]}>{q(`statuses.${row.qiwa_status}`)}</Badge> },
          { key: 'qiwa_documented_on', label: q('documented_on'), render: (row) => (row.qiwa_documented_on ? formatDate(row.qiwa_documented_on, locale) : '—') },
        ]}
      />
    </SuiteLayout>
  );
}
