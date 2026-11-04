import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import StatusBadge from '@/components/platform/StatusBadge';
import AdminLayout from '@/layouts/AdminLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useFilters } from '@/lib/useFilters';

export default function RequestsIndex({ requests, filters: initialFilters, options }) {
  const t = useT();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field) => t(`admin.requests.fields.${field}`);

  return (
    <AdminLayout title={t('admin.requests.title')}>
      <FilterBar
        filters={filters}
        setFilter={setFilter}
        selects={[
          { key: 'type', label: f('type'), options: options.types },
          { key: 'status', label: f('status'), options: options.statuses },
        ]}
      />
      <DataTable
        rows={requests}
        rowHref={(request) => `/admin/requests/${request.id}`}
        columns={[
          { key: 'name', label: f('name'), render: (request) => (
            <span><span className="font-medium">{request.name}</span><span className="block text-xs text-muted-foreground">{request.company ?? '—'}</span></span>
          ) },
          { key: 'email', label: f('email'), render: (request) => <span className="text-muted-foreground" dir="ltr">{request.email}</span> },
          { key: 'type', label: f('type'), render: (request) => <StatusBadge group="request_type" value={request.type} /> },
          { key: 'received', label: f('received'), render: (request) => formatDate(request.created_at, locale) },
          { key: 'status', label: f('status'), render: (request) => <StatusBadge group="request_status" value={request.status} /> },
        ]}
      />
    </AdminLayout>
  );
}
