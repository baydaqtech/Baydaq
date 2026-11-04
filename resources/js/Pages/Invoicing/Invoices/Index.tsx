import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

export interface InvoiceSummary { id: number; number: string | null; company_id: number; company: string | null; customer: string | null; type: string; kind: string; status: string; issue_date: string; subtotal: string; vat: string; total: string; [key: string]: unknown }

interface GroupTotals { companies: { company: string | null; external: string; intercompany: string }[]; group_external: string; intercompany: string }

export default function InvoicesIndex({ invoices, filters: initialFilters, statuses, types, group }: { invoices: Paginated<InvoiceSummary>; filters: Filters; statuses: Option[]; types: Option[]; group: GroupTotals }) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`invoicing.invoices.fields.${field}`);

  return (
    <SuiteLayout title={t('invoicing.invoices.title')}>
      <PageHeader title={t('invoicing.invoices.title')} actions={can('invoicing.invoices.create') && <Button asChild size="sm"><Link href={url('/invoicing/invoices/create')}><Plus data-icon="inline-start" />{t('invoicing.invoices.new')}</Link></Button>} />
      {group.companies.length > 1 && (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{t('invoicing.group.title')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-2 text-sm">
            <ul className="divide-y">
              {group.companies.map((row) => (
                <li key={row.company ?? ''} className="flex flex-wrap justify-between gap-2 py-1.5">
                  <span>{row.company}</span>
                  <span className="text-muted-foreground">{formatMoney(row.external, locale)}{Number(row.intercompany) > 0 && <> · {t('invoicing.intercompany')}: {formatMoney(row.intercompany, locale)}</>}</span>
                </li>
              ))}
            </ul>
            <p className="font-medium">{t('invoicing.group.total')}: {formatMoney(group.group_external, locale)} <span className="font-normal text-muted-foreground">({t('invoicing.group.eliminated', { amount: formatMoney(group.intercompany, locale) })})</span></p>
          </CardContent>
        </Card>
      )}
      <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'type', label: f('type'), options: types }, { key: 'status', label: f('status'), options: statuses }, { key: 'intercompany', label: t('invoicing.intercompany'), options: [{ value: 'exclude', label: t('invoicing.group.exclude') }, { value: 'only', label: t('invoicing.group.only') }] }]} />
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
          { key: 'status', label: f('status'), render: (invoice) => (
            <span className="flex flex-wrap items-center gap-1">
              <StatusBadge group="invoice_status" value={invoice.status} file="invoicing" />
              {Boolean(invoice.intercompany) && <Badge variant="outline">{t('invoicing.intercompany')}</Badge>}
              {Boolean(invoice.paid) && <Badge variant="outline" className="border-transparent bg-emerald-100 text-emerald-800">{t('invoicing.paid_online')}</Badge>}
            </span>
          ) },
        ]}
      />
    </SuiteLayout>
  );
}
