import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface Variant { id?: number; name_ar: string; name_en: string; sku: string | null; barcode: string | null; content: string | null; content_unit_id: number | null; content_unit?: string | null; unit_cost: string; is_active: boolean; on_hand?: string; name?: string }
interface Pack { id?: number; name_ar: string; name_en: string; factor: string }
interface Item { id: number; category_id: number | null; unit_id: number; name: string; name_ar: string; name_en: string; description: string | null; min_stock: string | null; unit: string; is_active: boolean; variants: Variant[]; packs: Pack[] }
interface UnitRow { id: number; name: string; name_ar: string; name_en: string; symbol: string; dimension: string; factor: string; is_active: boolean; [key: string]: unknown }

interface Props { items: Item[]; units: UnitRow[]; categories: Option[]; dimensions: Option[] }

const blankVariant = (): Variant => ({ name_ar: '', name_en: '', sku: '', barcode: '', content: '', content_unit_id: null, unit_cost: '0', is_active: true });

function ItemDialog({ item, units, categories }: { item?: Item; units: UnitRow[]; categories: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.inventory.fields.${field}`);
  const form = useForm({
    name_ar: item?.name_ar ?? '', name_en: item?.name_en ?? '', description: item?.description ?? '', unit_id: item?.unit_id ?? units[0]?.id ?? '',
    category_id: item?.category_id ?? '', min_stock: item?.min_stock ?? '', is_active: item?.is_active ?? true,
    variants: item?.variants.map(({ id, name_ar, name_en, sku, barcode, content, content_unit_id, unit_cost, is_active }) => ({ id, name_ar, name_en, sku: sku ?? '', barcode: barcode ?? '', content: content ?? '', content_unit_id, unit_cost, is_active })) ?? [blankVariant()],
    packs: item?.packs.map(({ id, name_ar, name_en, factor }) => ({ id, name_ar, name_en, factor })) ?? ([] as Pack[]),
  });
  const errors = form.errors as Record<string, string>;
  const unitOptions = units.filter((unit) => unit.is_active).map((unit) => ({ value: unit.id, label: `${unit.name} (${unit.symbol})` }));
  const setVariant = (index: number, patch: Partial<Variant>) => form.setData('variants', form.data.variants.map((variant, i) => (i === index ? { ...variant, ...patch } : variant)));
  const setPack = (index: number, patch: Partial<Pack>) => form.setData('packs', form.data.packs.map((pack, i) => (i === index ? { ...pack, ...patch } : pack)));

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data, category_id: data.category_id || null, min_stock: data.min_stock === '' ? null : data.min_stock, description: data.description || null,
      variants: data.variants.map((variant) => ({ ...variant, sku: variant.sku || null, barcode: variant.barcode || null, content: variant.content === '' ? null : variant.content, content_unit_id: variant.content_unit_id || null })),
    }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    item ? form.put(url(`/assets/inventory/items/${item.id}`), options) : form.post(url('/assets/inventory/items'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{item ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('assets.inventory.new_item')}</Button>}</DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-4xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{item ? item.name : t('assets.inventory.new_item')}</DialogTitle></DialogHeader>
          <FormField label={f('name_ar')} error={errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('unit')} error={errors.unit_id}><SelectInput value={form.data.unit_id} onChange={(value) => form.setData('unit_id', Number(value))} options={unitOptions} /></FormField>
          <FormField label={f('category')}><SelectInput value={form.data.category_id} onChange={(value) => form.setData('category_id', value === '' ? '' : Number(value))} options={categories} emptyLabel="—" /></FormField>
          <FormField label={f('min_stock')} error={errors.min_stock}><Input dir="ltr" inputMode="decimal" value={form.data.min_stock} onChange={(event) => form.setData('min_stock', event.target.value)} /></FormField>
          <div className="flex items-end"><SwitchField id="item-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} /></div>

          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{t('assets.inventory.variants')}</legend>
            {form.data.variants.map((variant, index) => (
              <div key={index} className="grid grid-cols-2 gap-2 rounded-md border p-2 md:grid-cols-[1fr_1fr_7rem_8rem_5rem_6rem_6rem_auto]">
                <Input aria-label={f('name_ar')} placeholder={f('name_ar')} value={variant.name_ar} onChange={(event) => setVariant(index, { name_ar: event.target.value })} />
                <Input aria-label={f('name_en')} placeholder={f('name_en')} dir="ltr" value={variant.name_en} onChange={(event) => setVariant(index, { name_en: event.target.value })} />
                <Input aria-label={f('sku')} placeholder={f('sku')} dir="ltr" value={variant.sku ?? ''} onChange={(event) => setVariant(index, { sku: event.target.value })} />
                <Input aria-label={f('barcode')} placeholder={f('barcode')} dir="ltr" value={variant.barcode ?? ''} onChange={(event) => setVariant(index, { barcode: event.target.value })} />
                <Input aria-label={f('content')} placeholder={f('content')} dir="ltr" inputMode="decimal" value={variant.content ?? ''} onChange={(event) => setVariant(index, { content: event.target.value })} />
                <SelectInput value={variant.content_unit_id ?? ''} onChange={(value) => setVariant(index, { content_unit_id: value === '' ? null : Number(value) })} options={units.map((unit) => ({ value: unit.id, label: unit.symbol }))} emptyLabel="—" />
                <Input aria-label={f('unit_cost')} placeholder={f('unit_cost')} dir="ltr" inputMode="decimal" value={variant.unit_cost} onChange={(event) => setVariant(index, { unit_cost: event.target.value })} />
                <Button type="button" size="icon-sm" variant="ghost" disabled={form.data.variants.length === 1} onClick={() => form.setData('variants', form.data.variants.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
              </div>
            ))}
            {Object.entries(errors).filter(([key]) => key.startsWith('variants')).slice(0, 1).map(([key, message]) => <p key={key} className="text-sm text-destructive">{message}</p>)}
            <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('variants', [...form.data.variants, blankVariant()])}><Plus data-icon="inline-start" />{t('assets.inventory.add_variant')}</Button>
          </fieldset>

          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{t('assets.inventory.packs')}</legend>
            {form.data.packs.map((pack, index) => (
              <div key={index} className="grid grid-cols-[1fr_1fr_8rem_auto] gap-2">
                <Input aria-label={f('name_ar')} placeholder={f('name_ar')} value={pack.name_ar} onChange={(event) => setPack(index, { name_ar: event.target.value })} />
                <Input aria-label={f('name_en')} placeholder={f('name_en')} dir="ltr" value={pack.name_en} onChange={(event) => setPack(index, { name_en: event.target.value })} />
                <Input aria-label={f('factor')} placeholder={f('factor')} dir="ltr" inputMode="decimal" value={pack.factor} onChange={(event) => setPack(index, { factor: event.target.value })} />
                <Button type="button" size="icon-sm" variant="ghost" onClick={() => form.setData('packs', form.data.packs.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
              </div>
            ))}
            <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('packs', [...form.data.packs, { name_ar: '', name_en: '', factor: '' }])}><Plus data-icon="inline-start" />{t('assets.inventory.add_pack')}</Button>
          </fieldset>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function UnitDialog({ unit, dimensions }: { unit?: UnitRow; dimensions: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.inventory.fields.${field}`);
  const form = useForm({ name_ar: unit?.name_ar ?? '', name_en: unit?.name_en ?? '', symbol: unit?.symbol ?? '', dimension: unit?.dimension ?? 'count', factor: unit?.factor ?? '1', is_active: unit?.is_active ?? true });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{unit ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('assets.inventory.new_unit')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; unit ? form.put(url(`/assets/inventory/units/${unit.id}`), options) : form.post(url('/assets/inventory/units'), options); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{unit ? unit.name : t('assets.inventory.new_unit')}</DialogTitle></DialogHeader>
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('symbol')} error={form.errors.symbol}><Input dir="ltr" value={form.data.symbol} onChange={(event) => form.setData('symbol', event.target.value)} /></FormField>
          <FormField label={f('dimension')}><SelectInput value={form.data.dimension} onChange={(value) => form.setData('dimension', value)} options={dimensions} /></FormField>
          <FormField className="sm:col-span-2" label={f('unit_factor')} error={form.errors.factor}><Input dir="ltr" inputMode="decimal" value={form.data.factor} onChange={(event) => form.setData('factor', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Items({ items, units, categories, dimensions }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const canEdit = can('assets.inventory.create', 'assets.inventory.update');

  return (
    <SuiteLayout title={t('assets.inventory.items')}>
      <PageHeader title={t('assets.inventory.items')} />
      <Tabs defaultValue="items">
        <TabsList>
          <TabsTrigger value="items">{t('assets.inventory.items')}</TabsTrigger>
          <TabsTrigger value="units">{t('assets.inventory.units')}</TabsTrigger>
        </TabsList>
        <TabsContent value="items" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><ItemDialog units={units} categories={categories} /></div>}
          {items.length === 0 && <Card><CardContent className="py-6 text-center text-sm text-muted-foreground">{t('assets.inventory.none')}</CardContent></Card>}
          {items.map((item) => (
            <Card key={item.id} className={item.is_active ? undefined : 'opacity-60'}>
              <CardContent className="flex flex-col gap-2 py-4">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-medium">{item.name_ar} <bdi className="text-xs text-muted-foreground">{item.name_en}</bdi></p>
                    <p className="text-xs text-muted-foreground">{t('assets.inventory.fields.unit')}: {item.unit}{item.packs.length > 0 && ` · ${item.packs.map((pack) => `${pack.name_ar} = ${formatNumber(pack.factor, locale)}`).join('، ')}`}</p>
                  </div>
                  {canEdit && <ItemDialog item={item} units={units} categories={categories} />}
                </div>
                <ul className="flex flex-col divide-y text-sm">
                  {item.variants.map((variant) => (
                    <li key={variant.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                      <span>{variant.name}{variant.content && <span className="text-muted-foreground"> · {formatNumber(variant.content, locale)} {variant.content_unit}</span>}{!variant.is_active && <Badge variant="outline" className="ms-2">—</Badge>}</span>
                      <span className="flex items-center gap-3 text-xs text-muted-foreground">
                        {variant.barcode && <span dir="ltr" className="font-mono">{variant.barcode}</span>}
                        <span className="tabular-nums">{formatMoney(variant.unit_cost, locale)} / {item.unit}</span>
                        <span className="font-medium text-foreground tabular-nums">{t('assets.inventory.on_hand')}: {formatNumber(variant.on_hand ?? '0', locale)} {item.unit}</span>
                      </span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ))}
        </TabsContent>
        <TabsContent value="units" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><UnitDialog dimensions={dimensions} /></div>}
          <DataTable<UnitRow>
            rows={units}
            columns={[
              { key: 'name', label: t('assets.inventory.fields.name_ar'), render: (unit) => <span>{unit.name_ar} <bdi className="text-xs text-muted-foreground">{unit.name_en}</bdi></span> },
              { key: 'symbol', label: t('assets.inventory.fields.symbol'), render: (unit) => <span dir="ltr" className="font-mono">{unit.symbol}</span> },
              { key: 'dimension', label: t('assets.inventory.fields.dimension'), render: (unit) => t(`assets.unit_dimension.${unit.dimension}`) },
              { key: 'factor', label: t('assets.inventory.fields.unit_factor'), render: (unit) => <span dir="ltr" className="tabular-nums">{formatNumber(unit.factor, locale)}</span> },
              { key: 'actions', label: '', className: 'text-end', render: (unit) => canEdit && <UnitDialog unit={unit} dimensions={dimensions} /> },
            ]}
          />
        </TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
