import { router } from '@inertiajs/react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatCard from '@/components/platform/StatCard';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface Item {
  product_id: number; name: string; quantity: number; share: number; net_each: number; cost: number | null; margin: number | null; total_margin: number | null;
  popular: boolean; class: 'star' | 'plowhorse' | 'puzzle' | 'dog' | 'unknown';
  [key: string]: unknown;
}

interface Props {
  items: Item[];
  average_margin: string | null;
  total_sold: number;
  filters: { from: string; to: string; brand: number | null };
  brands: Option[];
}

const TONE: Record<Item['class'], string> = {
  star: 'bg-emerald-100 text-emerald-800', plowhorse: 'bg-sky-100 text-sky-800', puzzle: 'bg-violet-100 text-violet-800', dog: 'bg-red-100 text-red-800', unknown: 'bg-muted text-muted-foreground',
};

/** Popularity × margin for each product over a period (MN3). */
export default function MenuEngineering({ items, average_margin: averageMargin, total_sold: totalSold, filters, brands }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const m = (key: string) => t(`pos.engineering.${key}`);
  const go = (patch: Partial<Props['filters']>) => router.get(url('/pos/reports/menu-engineering'), { ...filters, ...patch }, { preserveState: true, replace: true });
  const money = (value: number | null) => (value === null ? '—' : formatMoney(value, locale));

  return (
    <SuiteLayout title={m('title')}>
      <PageHeader title={m('title')} description={m('hint')} />
      <div className="mb-4 grid gap-3 sm:grid-cols-4 sm:items-end">
        <FormField label={m('from')}><Input type="date" dir="ltr" value={filters.from} onChange={(event) => go({ from: event.target.value })} /></FormField>
        <FormField label={m('to')}><Input type="date" dir="ltr" value={filters.to} onChange={(event) => go({ to: event.target.value })} /></FormField>
        {brands.length > 0 && <FormField label={m('brand')}><SelectInput value={filters.brand ?? ''} onChange={(value) => go({ brand: value ? Number(value) : null })} options={brands} emptyLabel={t('pos.orders.all_brands')} /></FormField>}
      </div>
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={m('items_sold')} value={formatNumber(totalSold, locale)} />
        <StatCard label={m('average_margin')} value={averageMargin ? formatMoney(averageMargin, locale) : '—'} />
      </div>
      <div className="mb-4 flex flex-wrap gap-2 text-xs">
        {(['star', 'plowhorse', 'puzzle', 'dog'] as const).map((kind) => (
          <span key={kind} className="flex items-center gap-1.5"><Badge variant="outline" className={cn('border-transparent', TONE[kind])}>{m(`class.${kind}`)}</Badge>{m(`advice.${kind}`)}</span>
        ))}
      </div>
      <DataTable<Item>
        rows={items}
        empty={m('empty')}
        rowKey={(item) => item.product_id}
        columns={[
          { key: 'name', label: m('product'), render: (item) => <span className="font-medium">{item.name}</span> },
          { key: 'quantity', label: m('sold'), render: (item) => <span>{formatNumber(item.quantity, locale)} <span className="text-xs text-muted-foreground">({formatPercent(item.share, locale)})</span></span> },
          { key: 'net_each', label: m('net_each'), render: (item) => money(item.net_each) },
          { key: 'cost', label: m('cost'), render: (item) => money(item.cost) },
          { key: 'margin', label: m('margin'), render: (item) => money(item.margin) },
          { key: 'class', label: m('class_label'), render: (item) => <Badge variant="outline" className={cn('border-transparent', TONE[item.class])}>{m(`class.${item.class}`)}</Badge> },
        ]}
      />
    </SuiteLayout>
  );
}
