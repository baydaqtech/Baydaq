import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Boxes, Coins, Plus, Settings2, UserCheck, Wallet } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

interface AssetRow { id: number; tag: string; name: string; category: string; serial: string | null; branch: string | null; status: string; holder: string | null; cost: string | null; book_value: string | null; [key: string]: unknown }

interface Props {
  assets: Paginated<AssetRow>;
  totals: { count: number; cost: string | null; book_value: string | null; assigned: number };
  filters: Filters;
  statuses: Option[];
  categories: Option[];
  branches: Option[];
}

export interface AssetFormData { branch_id: number | string; category_id: number | string; name_ar: string; name_en: string; serial: string; model: string; purchase_date: string; cost: string; salvage_value: string; useful_life_months: number | string; notes: string }

export function AssetFields({ form, branches, categories, showCosts }: { form: ReturnType<typeof useForm<AssetFormData>>; branches: Option[]; categories: Option[]; showCosts: boolean }) {
  const t = useT();
  const f = (field: string) => t(`assets.register.fields.${field}`);

  return (
    <>
      <FormField label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', Number(value))} options={branches} /></FormField>
      <FormField label={f('category')} error={form.errors.category_id}><SelectInput value={form.data.category_id} onChange={(value) => form.setData('category_id', Number(value))} options={categories} /></FormField>
      <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
      <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
      <FormField label={f('serial')} error={form.errors.serial}><Input dir="ltr" value={form.data.serial} onChange={(event) => form.setData('serial', event.target.value)} /></FormField>
      <FormField label={f('model')} error={form.errors.model}><Input dir="ltr" value={form.data.model} onChange={(event) => form.setData('model', event.target.value)} /></FormField>
      <FormField label={f('purchase_date')} error={form.errors.purchase_date}><Input dir="ltr" type="date" value={form.data.purchase_date} onChange={(event) => form.setData('purchase_date', event.target.value)} /></FormField>
      <FormField label={f('useful_life_months')} error={form.errors.useful_life_months}><Input dir="ltr" type="number" min={1} value={form.data.useful_life_months} onChange={(event) => form.setData('useful_life_months', event.target.value === '' ? '' : Number(event.target.value))} /></FormField>
      {showCosts && <FormField label={f('cost')} error={form.errors.cost}><Input dir="ltr" inputMode="decimal" value={form.data.cost} onChange={(event) => form.setData('cost', event.target.value)} /></FormField>}
      {showCosts && <FormField label={f('salvage_value')} error={form.errors.salvage_value}><Input dir="ltr" inputMode="decimal" value={form.data.salvage_value} onChange={(event) => form.setData('salvage_value', event.target.value)} /></FormField>}
      <FormField className="sm:col-span-2" label={f('notes')}><Textarea rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></FormField>
    </>
  );
}

export function blankToNull(data: AssetFormData) {
  return { ...data, serial: data.serial || null, model: data.model || null, purchase_date: data.purchase_date || null, useful_life_months: data.useful_life_months === '' ? null : data.useful_life_months, cost: data.cost || null, salvage_value: data.salvage_value || null, notes: data.notes || null };
}

function NewAsset({ branches, categories, showCosts }: { branches: Option[]; categories: Option[]; showCosts: boolean }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm<AssetFormData>({ branch_id: branches[0]?.value ?? '', category_id: categories[0]?.value ?? '', name_ar: '', name_en: '', serial: '', model: '', purchase_date: '', cost: '', salvage_value: '0', useful_life_months: '', notes: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Plus data-icon="inline-start" />{t('assets.register.new')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <form onSubmit={(event) => { event.preventDefault(); form.transform(blankToNull); form.post(url('/assets')); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.register.new')}</DialogTitle></DialogHeader>
          <AssetFields form={form} branches={branches} categories={categories} showCosts={showCosts} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function AssetsIndex({ assets, totals, filters: initialFilters, statuses, categories, branches }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [filters, setFilter] = useFilters(initialFilters);
  const showCosts = totals.cost !== null;
  const f = (field: string) => t(`assets.register.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.register.title')}>
      <PageHeader
        title={t('assets.register.title')}
        actions={
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" asChild><Link href={url('/assets/categories')}><Settings2 data-icon="inline-start" />{t('assets.categories.title')}</Link></Button>
            {can('assets.register.create') && categories.length > 0 && branches.length > 0 && <NewAsset branches={branches} categories={categories} showCosts={showCosts} />}
          </div>
        }
      />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t('assets.register.count')} value={formatNumber(totals.count, locale)} icon={Boxes} />
        <StatCard label={t('assets.register.assigned')} value={formatNumber(totals.assigned, locale)} icon={UserCheck} />
        {showCosts && <StatCard label={t('assets.register.total_cost')} value={formatMoney(totals.cost!, locale)} icon={Coins} />}
        {showCosts && <StatCard label={t('assets.register.total_book')} value={formatMoney(totals.book_value!, locale)} icon={Wallet} />}
      </div>
      <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'status', label: f('status'), options: statuses }, { key: 'category', label: f('category'), options: categories }]} />
      <DataTable<AssetRow>
        rows={assets}
        rowHref={(asset) => url(`/assets/assets/${asset.id}`)}
        empty={t('assets.register.none')}
        columns={[
          { key: 'tag', label: f('tag'), render: (asset) => <span dir="ltr" className="font-mono text-xs">{asset.tag}</span> },
          { key: 'name', label: f('name'), render: (asset) => <span><span className="block font-medium">{asset.name}</span><span className="text-xs text-muted-foreground">{asset.category}{asset.serial ? ` · ${asset.serial}` : ''}</span></span> },
          { key: 'branch', label: f('branch'), render: (asset) => asset.branch },
          { key: 'holder', label: f('holder'), render: (asset) => asset.holder ?? '—' },
          ...(showCosts ? [{ key: 'book', label: f('book_value'), render: (asset: AssetRow) => <span className="tabular-nums">{formatMoney(asset.book_value!, locale)}</span> }] : []),
          { key: 'status', label: f('status'), render: (asset) => <StatusBadge file="assets" group="asset_status" value={asset.status} /> },
        ]}
      />
    </SuiteLayout>
  );
}
