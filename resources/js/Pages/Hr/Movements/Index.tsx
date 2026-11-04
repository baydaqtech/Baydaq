import { Link } from '@inertiajs/react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { MovementResource } from '@/types/hr';
import type { Option, Paginated } from '@/types/resources';

interface Props {
  movements: Paginated<MovementResource>;
  filters: Filters;
  statuses: Option[];
  types: Option[];
}

export default function MovementsIndex({ movements, filters: initialFilters, statuses, types }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`hr.movements.fields.${field}`);

  return (
    <SuiteLayout title={t('hr.movements.title')}>
      <PageHeader title={t('hr.movements.title')} />
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'type', label: f('type'), options: types }, { key: 'status', label: f('status'), options: statuses }]} />
      <DataTable<MovementResource>
        rows={movements}
        empty={t('hr.movements.none')}
        columns={[
          { key: 'employee', label: f('employee'), render: (movement) => <Link href={url(`/hr/employees/${movement.employee_id}`)} className="font-medium hover:underline">{movement.employee}</Link> },
          { key: 'type', label: f('type'), render: (movement) => <StatusBadge group="movement_type" value={movement.type} file="hr" /> },
          { key: 'route', label: f('to_branch'), render: (movement) => `${movement.from_branch} → ${movement.to_branch}` },
          { key: 'dates', label: f('starts_on'), render: (movement) => `${formatDate(movement.starts_on, locale)}${movement.ends_on ? ` → ${formatDate(movement.ends_on, locale)}` : ''}` },
          { key: 'status', label: f('status'), render: (movement) => <StatusBadge group="movement_status" value={movement.status} file="hr" /> },
        ]}
      />
    </SuiteLayout>
  );
}
