import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { EmployeeResource } from '@/types/hr';
import type { Option, Paginated } from '@/types/resources';

interface Props {
  employees: Paginated<EmployeeResource>;
  branches: Record<number, string>;
  filters: Filters;
  statuses: Option[];
  sections: Option[];
  seats: { used: number; total: number | null };
}

export default function EmployeesIndex({ employees, branches, filters: initialFilters, statuses, sections, seats }: Props) {
  const t = useT();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`hr.employees.fields.${field}`);

  return (
    <SuiteLayout title={t('hr.employees.title')}>
      <PageHeader
        title={t('hr.employees.title')}
        description={seats.total !== null ? t('hr.employees.seats', { used: seats.used, total: seats.total }) : undefined}
        actions={can('hr.employees.create') && (
          <Button asChild size="sm"><Link href={url('/hr/employees/create')}><Plus data-icon="inline-start" />{t('hr.employees.new')}</Link></Button>
        )}
      />
      <FilterBar
        filters={filters}
        setFilter={setFilter}
        selects={[
          { key: 'status', label: f('status'), options: statuses },
          { key: 'section_id', label: f('section'), options: sections },
        ]}
      />
      <DataTable<EmployeeResource>
        rows={employees}
        rowHref={(employee) => url(`/hr/employees/${employee.id}`)}
        columns={[
          { key: 'employee_number', label: f('employee_number'), render: (employee) => <span className="font-mono text-xs" dir="ltr">{employee.employee_number}</span> },
          { key: 'name', label: f('name_ar'), render: (employee) => <span className="font-medium">{employee.name}</span> },
          { key: 'job', label: f('job'), render: (employee) => <span className="text-muted-foreground">{employee.job ?? '—'}{employee.section && <span className="block text-xs">{employee.section}</span>}</span> },
          { key: 'branch', label: f('branch'), render: (employee) => branches[employee.branch_id] ?? '—' },
          { key: 'status', label: f('status'), render: (employee) => <StatusBadge group="employee_status" value={employee.status} file="hr" /> },
        ]}
      />
    </SuiteLayout>
  );
}
