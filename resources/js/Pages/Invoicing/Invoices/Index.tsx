import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

export interface InvoiceSummary { id: number; number: string | null; company_id: number; company: string | null; customer: string | null; type: string; kind: string; status: string; issue_date: string; subtotal: string; vat: string; total: string; [key: string]: unknown }

export default function InvoicesIndex({ invoices, filters: initialFilters, statuses, types }: { invoices: Paginated<InvoiceSummary>; filters: Filters; statuses: Option[]; types: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`invoicing.invoices.fields.${field}`);

  return (
    <SuiteLayout title={t('invoicing.invoices.title')}>
      <PageHeader title={t('invoicing.invoices.title')} actions={can('invoicing.invoices.create') && <Button asChild size="sm"><Link href={url('/invoicing/invoices/create')}><Plus data-icon="inline-start" />{t('invoicing.invoices.new')}</Link></Button>} />
      <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'type', label: f('type'), options: types }, { key: 'status', label: f('status'), options: statuses }]} />
      <DataTable<InvoiceSummary>
        rows={invoices}
        empty={t('invoicing.invoices.none')}
        rowHref={(invoice) => url(`/invoicing/invoices/${invoice.id}`)}
        columns={[
          { key: 'number', label: f('number'), render: (invoice) => <span className="font-mono text-xs" dir="ltr">{invoice.number ?? '—'}</span> },
          { key: 'customer', label: f('customer'), render: (invoice) => invoice.customer ?? t('invoicing.invoices.customer_walkin') },
          { key: 'kind', label: f('kind'), render: (invoice) => <span className="text-muted-foreground">{t(`invoicing.invoice_kind.${invoice.kind}`)} · {t(`invoicing.invoice_type.${invoice.type}`)}</span> },
          { key: 'issue_date', label: f('issue_date'), render: (invoice) => formatDate(invoice.issue_date, locale) },
          { key: 'total', label: f('total'), render: (invoice) => <strong>{formatMoney(invoice.total, locale)}</strong> },
          { key: 'status', label: f('status'), render: (invoice) => <StatusBadge group="invoice_status" value={invoice.status} file="invoicing" /> },
        ]}
      />
    </SuiteLayout>
  );
}
