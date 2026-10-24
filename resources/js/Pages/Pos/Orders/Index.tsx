import { Receipt, ShoppingBag } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { OrderResource } from '@/types/pos';
import type { Option, Paginated } from '@/types/resources';

interface Props {
  orders: Paginated<OrderResource>;
  filters: Filters;
  statuses: Option[];
  totals: { count: number; paid: string };
}

export default function OrdersIndex({ orders, filters: initialFilters, statuses, totals }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`pos.orders.fields.${field}`);

  return (
    <SuiteLayout title={t('pos.orders.title')}>
      <PageHeader title={t('pos.orders.title')} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <StatCard label={t('pos.orders.count')} value={formatNumber(totals.count, locale)} icon={ShoppingBag} />
        <StatCard label={t('pos.orders.paid_total')} value={formatMoney(totals.paid, locale)} icon={Receipt} />
      </div>
      <div className="flex flex-wrap items-start gap-2">
        <Input type="date" dir="ltr" className="w-44" aria-label={t('pos.orders.date')} value={String(filters.date ?? '')} onChange={(event) => setFilter('date', event.target.value)} />
        <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: f('status'), options: statuses }]} />
      </div>
      <DataTable<OrderResource>
        rows={orders}
        empty={t('pos.orders.none')}
        columns={[
          { key: 'number', label: f('number'), render: (order) => <span className="font-medium tabular-nums">#{order.number}</span> },
          { key: 'time', label: f('time'), render: (order) => <span dir="ltr" className="tabular-nums">{new Date(order.created_at).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}</span> },
          { key: 'type', label: f('type'), render: (order) => <span>{t(`pos.order_type.${order.type}`)}<span className="block text-xs text-muted-foreground">{t(`pos.orders.channel.${order.channel}`)}{order.branch ? ` · ${order.branch}` : ''}</span></span> },
          { key: 'items', label: f('items'), render: (order) => <span className="text-sm">{order.lines.map((line) => `${Number(line.quantity)}× ${line.name}`).join('، ')}</span> },
          { key: 'total', label: f('total'), render: (order) => <span className="tabular-nums">{formatMoney(order.total, locale)}</span> },
          { key: 'status', label: f('status'), render: (order) => <StatusBadge file="pos" group="order_status" value={order.status} /> },
          { key: 'invoice', label: f('invoice'), render: (order) => (order.invoice_id ? <a className="text-sm underline" href={url(`/invoicing/invoices/${order.invoice_id}`)}>{t('common.actions.view')}</a> : '—') },
        ]}
      />
    </SuiteLayout>
  );
}
