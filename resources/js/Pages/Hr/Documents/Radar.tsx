import { Link } from '@inertiajs/react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import { Badge } from '@/components/ui/badge';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface Row {
  id: number;
  employee_id: number;
  employee: string;
  type: string;
  expires_on: string;
  days_left: number;
  bucket: 'expired' | '30' | '60' | '90';
  cost: string | null;
  renewal: { id: number; status: 'open' | 'done' | 'cancelled' } | null;
  [key: string]: unknown;
}

interface Props {
  buckets: Record<string, { count: number; cost: string }>;
  rows: Row[];
}

const TONE: Record<string, string> = { expired: 'bg-red-100 text-red-800', '30': 'bg-amber-100 text-amber-800', '60': 'bg-yellow-50 text-yellow-800', '90': 'bg-muted text-muted-foreground' };

/** Documents running out over the next 90 days, what renewing them costs, and the renewal tasks (H6). */
export default function Radar({ buckets, rows }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const r = (key: string, replace?: Record<string, string>) => t(`hr.documents.radar.${key}`, replace);

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('hint')} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {Object.entries(buckets).map(([bucket, value]) => (
          <StatCard key={bucket} label={r(`buckets.${bucket}`)} value={<>{formatNumber(value.count, locale)}{Number(value.cost) > 0 && <span className="ms-2 text-sm font-normal text-muted-foreground">{formatMoney(value.cost, locale)}</span>}</>} />
        ))}
      </div>
      <DataTable<Row>
        rows={rows}
        empty={r('empty')}
        columns={[
          { key: 'employee', label: t('hr.employees.title'), render: (row) => <Link href={url(`/hr/employees/${row.employee_id}`)} className="font-medium hover:underline">{row.employee}<span className="block text-xs text-muted-foreground">{row.type}</span></Link> },
          { key: 'expires_on', label: t('hr.documents.fields.expires_on'), render: (row) => (
            <span className="flex flex-col gap-1">
              {formatDate(row.expires_on, locale)}
              <Badge variant="outline" className={cn('w-fit border-transparent', TONE[row.bucket])}>{row.days_left < 0 ? r('expired_ago', { days: String(-row.days_left) }) : r('days_left', { days: String(row.days_left) })}</Badge>
            </span>
          ) },
          { key: 'cost', label: r('cost'), render: (row) => (row.cost ? formatMoney(row.cost, locale) : '—') },
          { key: 'renewal', label: '', render: (row) => (
            <span className="flex flex-wrap items-center gap-2">
              <span className="text-xs text-muted-foreground">{row.renewal ? r(`task.${row.renewal.status}`) : r('no_task')}</span>
              {row.renewal?.status === 'open' && can('hr.documents.update') && (
                <>
                  <ConfirmAction href={url(`/hr/renewals/${row.renewal.id}`)} method="put" data={{ status: 'done' }} size="sm" variant="outline" title={r('mark_done')}>{r('mark_done')}</ConfirmAction>
                  <ConfirmAction href={url(`/hr/renewals/${row.renewal.id}`)} method="put" data={{ status: 'cancelled' }} size="sm" variant="ghost" title={r('mark_cancelled')}>{r('mark_cancelled')}</ConfirmAction>
                </>
              )}
            </span>
          ) },
        ]}
      />
    </SuiteLayout>
  );
}
