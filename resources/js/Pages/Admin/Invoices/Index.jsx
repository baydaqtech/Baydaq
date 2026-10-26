import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useFilters } from '@/lib/useFilters';

export default function InvoicesIndex({ invoices, filters: initialFilters, options }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field) => t(`admin.invoices.fields.${field}`);

  return (
    <AdminLayout title={t('admin.invoices.title')}>
      <PageHeader
        actions={can('invoices.manage') && (
          <Button asChild><Link href="/admin/invoices/create"><Plus data-icon="inline-start" />{t('admin.invoices.new')}</Link></Button>
        )}
      />
      <FilterBar
        filters={filters}
        setFilter={setFilter}
        selects={[
          { key: 'client_id', label: f('client'), options: options.clients },
          { key: 'status', label: f('status'), options: options.statuses },
        ]}
      />
      <DataTable
        rows={invoices}
        rowHref={(invoice) => `/admin/invoices/${invoice.id}`}
        columns={[
          { key: 'number', label: f('number'), render: (invoice) => <span className="font-mono text-xs" dir="ltr">{invoice.number}</span> },
          { key: 'client', label: f('client'), render: (invoice) => (
            <span>{invoice.client?.name}{invoice.project && <span className="block text-xs text-muted-foreground">{invoice.project.name}</span>}</span>
          ) },
          { key: 'issue_date', label: f('issue_date'), render: (invoice) => formatDate(invoice.issue_date, locale) },
          { key: 'due_date', label: f('due_date'), render: (invoice) => formatDate(invoice.due_date, locale) },
          { key: 'total', label: f('total'), render: (invoice) => <span className="font-medium">{formatMoney(invoice.total, locale)}</span> },
          { key: 'status', label: f('status'), render: (invoice) => <StatusBadge group="invoice_status" value={invoice.status} /> },
        ]}
      />
    </AdminLayout>
  );
}
