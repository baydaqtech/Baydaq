import { Link } from '@inertiajs/react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { LeaveDecision, type LeaveItem } from '@/components/hr/LeaveTab';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

type Row = LeaveItem & { employee: string; can_decide: boolean; [key: string]: unknown };

export default function LeaveIndex({ requests, filters: initialFilters, statuses }: { requests: Paginated<Row>; filters: Filters; statuses: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`hr.leave.fields.${field}`);

  return (
    <SuiteLayout title={t('hr.leave.title')}>
      <PageHeader title={t('hr.leave.title')} />
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: f('status'), options: statuses }]} />
      <DataTable<Row>
        rows={requests}
        empty={t('hr.leave.none')}
        columns={[
          { key: 'employee', label: f('employee'), render: (row) => <Link href={url(`/hr/employees/${row.employee_id}`)} className="font-medium hover:underline">{row.employee}</Link> },
          { key: 'type', label: f('type') },
          { key: 'period', label: f('starts_on'), render: (row) => `${formatDate(row.starts_on, locale)} → ${formatDate(row.ends_on, locale)}` },
          { key: 'days', label: f('days') },
          { key: 'status', label: f('status'), render: (row) => <StatusBadge group="leave_status" value={row.status} file="hr" /> },
          { key: 'actions', label: '', className: 'text-end', render: (row) => row.can_decide && <LeaveDecision leave={row} /> },
        ]}
      />
    </SuiteLayout>
  );
}
