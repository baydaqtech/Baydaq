import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { useLocale, useT } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { useFilters } from '@/lib/useFilters';

export default function ClientsIndex({ clients, filters: initialFilters, statuses }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);

  return (
    <AdminLayout title={t('admin.clients.title')}>
      <PageHeader
        actions={can('clients.manage') && (
          <Button asChild><Link href="/admin/clients/create"><Plus data-icon="inline-start" />{t('admin.clients.new')}</Link></Button>
        )}
      />
      <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'status', label: t('admin.clients.fields.status'), options: statuses }]} />
      <DataTable
        rows={clients}
        rowHref={(client) => `/admin/clients/${client.id}`}
        columns={[
          { key: 'name', label: t('admin.clients.fields.name'), render: (client) => <span className="font-medium">{client.name}</span> },
          { key: 'contact', label: t('admin.clients.fields.contact'), render: (client) => (
            <span className="text-muted-foreground">{client.contact_name ?? '—'}{client.contact_email && <span className="block text-xs" dir="ltr">{client.contact_email}</span>}</span>
          ) },
          { key: 'projects_count', label: t('admin.clients.projects'), render: (client) => formatNumber(client.projects_count, locale) },
          { key: 'status', label: t('admin.clients.fields.status'), render: (client) => <StatusBadge group="client_status" value={client.status} /> },
        ]}
      />
    </AdminLayout>
  );
}
