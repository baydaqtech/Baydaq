import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { useT } from '@/lib/i18n';
import { useFilters } from '@/lib/useFilters';

export default function ProjectsIndex({ projects, filters: initialFilters, options }) {
  const t = useT();
  const can = useCan();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field) => t(`admin.projects.fields.${field}`);

  return (
    <AdminLayout title={t('admin.projects.title')}>
      <PageHeader
        actions={can('projects.manage') && (
          <Button asChild><Link href="/admin/projects/create"><Plus data-icon="inline-start" />{t('admin.projects.new')}</Link></Button>
        )}
      />
      <FilterBar
        filters={filters}
        setFilter={setFilter}
        selects={[
          { key: 'client_id', label: f('client'), options: options.clients },
          { key: 'type', label: f('type'), options: options.types },
          { key: 'status', label: f('status'), options: options.statuses },
        ]}
      />
      <DataTable
        rows={projects}
        rowHref={(project) => `/admin/projects/${project.slug}`}
        columns={[
          { key: 'name', label: f('name'), render: (project) => <span className="font-medium">{project.name}</span> },
          { key: 'client', label: f('client'), render: (project) => <span className="text-muted-foreground">{project.client?.name}</span> },
          { key: 'type', label: f('type'), render: (project) => <StatusBadge group="project_type" value={project.type} /> },
          { key: 'status', label: f('status'), render: (project) => <StatusBadge group="project_status" value={project.status} /> },
          { key: 'progress', label: f('progress'), className: 'w-36', render: (project) => (
            <div className="flex items-center gap-2"><Progress value={project.progress} className="h-1.5" /><span className="text-xs text-muted-foreground">{project.progress}%</span></div>
          ) },
        ]}
      />
    </AdminLayout>
  );
}
