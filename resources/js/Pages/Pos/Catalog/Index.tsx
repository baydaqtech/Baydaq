import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';
import type { Option } from '@/types/resources';

interface Category { id: number; company_id: number; brand_id: number | null; name: string; name_ar: string; name_en: string; color: string | null; sort_order: number; is_active: boolean; [key: string]: unknown }
interface Modifier { name: string; name_ar: string; name_en: string; price: string }
interface Group { id: number; company_id: number; name: string; name_ar: string; name_en: string; min: number; max: number; modifiers: Modifier[]; [key: string]: unknown }
interface Override { branch_id: number; price: string | null; is_available: boolean }
interface Product {
  id: number; company_id: number; category_id: number | null; category: string | null; sku: string | null; name: string; name_ar: string; name_en: string;
  description_ar: string | null; description_en: string | null; price: string; vat_rate: string; calories: number | null; high_salt: boolean; caffeine_mg: number | null;
  allergens: string[] | null; is_active: boolean; sold_by_weight: boolean; sort_order: number; modifier_group_ids: number[]; branch_overrides: Override[];
  [key: string]: unknown;
}

interface Props {
  companies: Option[];
  brands: ScopedOption[];
  branches: ScopedOption[];
  categories: Category[];
  groups: Group[];
  products: Product[];
}

const VAT_RATES: Option[] = [{ value: '15', label: '15%' }, { value: '5', label: '5%' }, { value: '0', label: '0%' }];

function useField() {
  const t = useT();
  return (field: string) => t(`pos.catalog.fields.${field}`);
}

function Trigger({ editing, label }: { editing: boolean; label: string }) {
  const t = useT();
  return editing ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{label}</Button>;
}

function NameFields({ form }: { form: { data: { name_ar: string; name_en: string }; errors: Partial<Record<string, string>>; setData: (key: 'name_ar' | 'name_en', value: string) => void } }) {
  const f = useField();
  return (
    <>
      <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
      <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
    </>
  );
}

function CategoryDialog({ category, companies, brands }: { category?: Category; companies: Option[]; brands: ScopedOption[] }) {
  const t = useT();
  const f = useField();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ company_id: category?.company_id ?? companies[0]?.value ?? '', brand_id: category?.brand_id ?? '', name_ar: category?.name_ar ?? '', name_en: category?.name_en ?? '', color: category?.color ?? '', sort_order: category?.sort_order ?? 0, is_active: category?.is_active ?? true });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, brand_id: data.brand_id || null, color: data.color || null }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    category ? form.put(url(`/pos/catalog/categories/${category.id}`), options) : form.post(url('/pos/catalog/categories'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Trigger editing={!!category} label={t('pos.catalog.new_category')} /></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{category ? category.name : t('pos.catalog.new_category')}</DialogTitle></DialogHeader>
          <FormField label={f('company')} error={form.errors.company_id}><SelectInput value={form.data.company_id} onChange={(value) => form.setData((data) => ({ ...data, company_id: Number(value), brand_id: '' }))} options={companies} /></FormField>
          <FormField label={f('brand')} error={form.errors.brand_id}><SelectInput value={form.data.brand_id} onChange={(value) => form.setData('brand_id', value === '' ? '' : Number(value))} options={brands.filter((brand) => brand.company_id === Number(form.data.company_id))} emptyLabel="—" /></FormField>
          <NameFields form={form} />
          <FormField label={f('color')} error={form.errors.color}><Input type="color" value={form.data.color || '#c8a84b'} onChange={(event) => form.setData('color', event.target.value)} /></FormField>
          <div className="flex items-end"><SwitchField id="category-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} /></div>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function GroupDialog({ group, companies }: { group?: Group; companies: Option[] }) {
  const t = useT();
  const f = useField();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({
    company_id: group?.company_id ?? companies[0]?.value ?? '', name_ar: group?.name_ar ?? '', name_en: group?.name_en ?? '', min: group?.min ?? 0, max: group?.max ?? 1,
    modifiers: group?.modifiers.map(({ name_ar, name_en, price }) => ({ name_ar, name_en, price })) ?? [{ name_ar: '', name_en: '', price: '0' }],
  });

  function setModifier(index: number, patch: Partial<Modifier>) {
    form.setData('modifiers', form.data.modifiers.map((modifier, i) => (i === index ? { ...modifier, ...patch } : modifier)));
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    group ? form.put(url(`/pos/catalog/groups/${group.id}`), options) : form.post(url('/pos/catalog/groups'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Trigger editing={!!group} label={t('pos.catalog.new_group')} /></DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{group ? group.name : t('pos.catalog.new_group')}</DialogTitle></DialogHeader>
          {!group && <FormField className="sm:col-span-2" label={f('company')} error={form.errors.company_id}><SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>}
          <NameFields form={form} />
          <FormField label={f('min')} error={form.errors.min}><Input dir="ltr" type="number" min={0} value={form.data.min} onChange={(event) => form.setData('min', Number(event.target.value))} /></FormField>
          <FormField label={f('max')} error={form.errors.max}><Input dir="ltr" type="number" min={1} value={form.data.max} onChange={(event) => form.setData('max', Number(event.target.value))} /></FormField>
          <div className="flex flex-col gap-2 sm:col-span-2">
            {form.data.modifiers.map((modifier, index) => (
              <div key={index} className="grid grid-cols-[1fr_1fr_6rem_auto] gap-2">
                <Input aria-label={f('name_ar')} placeholder={f('name_ar')} value={modifier.name_ar} onChange={(event) => setModifier(index, { name_ar: event.target.value })} />
                <Input aria-label={f('name_en')} placeholder={f('name_en')} dir="ltr" value={modifier.name_en} onChange={(event) => setModifier(index, { name_en: event.target.value })} />
                <Input aria-label={f('price')} dir="ltr" inputMode="decimal" value={modifier.price} onChange={(event) => setModifier(index, { price: event.target.value })} />
                <Button type="button" size="icon-sm" variant="ghost" disabled={form.data.modifiers.length === 1} onClick={() => form.setData('modifiers', form.data.modifiers.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
              </div>
            ))}
            {Object.entries(form.errors).filter(([key]) => key.startsWith('modifiers')).slice(0, 1).map(([key, message]) => <p key={key} className="text-sm text-destructive">{message}</p>)}
            <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('modifiers', [...form.data.modifiers, { name_ar: '', name_en: '', price: '0' }])}><Plus data-icon="inline-start" />{t('pos.catalog.add_option')}</Button>
          </div>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ProductDialog({ product, companies, categories, groups, branches }: { product?: Product; companies: Option[]; categories: Category[]; groups: Group[]; branches: ScopedOption[] }) {
  const t = useT();
  const f = useField();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({
    company_id: product?.company_id ?? companies[0]?.value ?? '', category_id: product?.category_id ?? '', sku: product?.sku ?? '',
    name_ar: product?.name_ar ?? '', name_en: product?.name_en ?? '', description_ar: product?.description_ar ?? '', description_en: product?.description_en ?? '',
    price: product?.price ?? '', vat_rate: product ? String(Number(product.vat_rate)) : '15',
    calories: product?.calories ?? '', high_salt: product?.high_salt ?? false, caffeine_mg: product?.caffeine_mg ?? '', allergens: (product?.allergens ?? []).join(', '),
    is_active: product?.is_active ?? true, sold_by_weight: product?.sold_by_weight ?? false, sort_order: product?.sort_order ?? 0,
    modifier_group_ids: product?.modifier_group_ids ?? [],
    branch_overrides: product?.branch_overrides ?? [],
  });
  const companyId = Number(form.data.company_id);

  function override(branchId: number): Override {
    return form.data.branch_overrides.find((row) => row.branch_id === branchId) ?? { branch_id: branchId, price: null, is_available: true };
  }

  function setOverride(branchId: number, patch: Partial<Override>) {
    const next = { ...override(branchId), ...patch };
    const rest = form.data.branch_overrides.filter((row) => row.branch_id !== branchId);
    form.setData('branch_overrides', next.is_available && !next.price ? rest : [...rest, next]);
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      category_id: data.category_id || null, sku: data.sku || null, calories: data.calories === '' ? null : data.calories, caffeine_mg: data.caffeine_mg === '' ? null : data.caffeine_mg,
      allergens: data.allergens.split(/[,،]/).map((item) => item.trim()).filter(Boolean),
      branch_overrides: data.branch_overrides.map((row) => ({ ...row, price: row.price || null })),
    }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    product ? form.put(url(`/pos/catalog/products/${product.id}`), options) : form.post(url('/pos/catalog/products'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Trigger editing={!!product} label={t('pos.catalog.new_product')} /></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-3xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{product ? product.name : t('pos.catalog.new_product')}</DialogTitle></DialogHeader>
          {!product && <FormField label={f('company')} error={form.errors.company_id}><SelectInput value={form.data.company_id} onChange={(value) => form.setData((data) => ({ ...data, company_id: Number(value), category_id: '', modifier_group_ids: [], branch_overrides: [] }))} options={companies} /></FormField>}
          <FormField label={f('category')} error={form.errors.category_id}><SelectInput value={form.data.category_id} onChange={(value) => form.setData('category_id', value === '' ? '' : Number(value))} options={categories.filter((category) => category.company_id === companyId).map((category) => ({ value: category.id, label: category.name }))} emptyLabel={t('pos.catalog.uncategorised')} /></FormField>
          <NameFields form={form} />
          <FormField label={f('description_ar')}><Textarea rows={2} value={form.data.description_ar} onChange={(event) => form.setData('description_ar', event.target.value)} /></FormField>
          <FormField label={f('description_en')}><Textarea dir="ltr" rows={2} value={form.data.description_en} onChange={(event) => form.setData('description_en', event.target.value)} /></FormField>
          <FormField label={f('price')} error={form.errors.price}><Input dir="ltr" inputMode="decimal" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} /></FormField>
          <FormField label={f('vat_rate')} error={form.errors.vat_rate}><SelectInput value={form.data.vat_rate} onChange={(value) => form.setData('vat_rate', value)} options={VAT_RATES} /></FormField>
          <FormField label={f('sku')} error={form.errors.sku}><Input dir="ltr" value={form.data.sku} onChange={(event) => form.setData('sku', event.target.value)} /></FormField>
          <FormField label={f('calories')} error={form.errors.calories}><Input dir="ltr" type="number" min={0} value={form.data.calories} onChange={(event) => form.setData('calories', event.target.value === '' ? '' : Number(event.target.value))} /></FormField>
          <FormField label={f('caffeine_mg')} error={form.errors.caffeine_mg}><Input dir="ltr" type="number" min={0} value={form.data.caffeine_mg} onChange={(event) => form.setData('caffeine_mg', event.target.value === '' ? '' : Number(event.target.value))} /></FormField>
          <FormField label={f('allergens')}><Input value={form.data.allergens} onChange={(event) => form.setData('allergens', event.target.value)} /></FormField>
          <div className="flex flex-wrap gap-4 sm:col-span-2">
            <SwitchField id="product-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
            <SwitchField id="product-salt" label={f('high_salt')} checked={form.data.high_salt} onChange={(value: boolean) => form.setData('high_salt', value)} />
            <SwitchField id="product-weight" label={f('sold_by_weight')} checked={form.data.sold_by_weight} onChange={(value: boolean) => form.setData('sold_by_weight', value)} />
          </div>
          {groups.some((group) => group.company_id === companyId) && (
            <fieldset className="flex flex-col gap-2 sm:col-span-2">
              <legend className="mb-1 text-sm font-medium">{f('modifier_groups')}</legend>
              <div className="flex flex-wrap gap-3">
                {groups.filter((group) => group.company_id === companyId).map((group) => (
                  <label key={group.id} className="flex items-center gap-2 text-sm">
                    <Checkbox checked={form.data.modifier_group_ids.includes(group.id)} onCheckedChange={(checked) => form.setData('modifier_group_ids', checked ? [...form.data.modifier_group_ids, group.id] : form.data.modifier_group_ids.filter((id) => id !== group.id))} />
                    {group.name}
                  </label>
                ))}
              </div>
            </fieldset>
          )}
          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{t('pos.catalog.branch_overrides')}</legend>
            {branches.filter((branch) => branch.company_id === companyId).map((branch) => {
              const row = override(Number(branch.value));
              return (
                <div key={branch.value} className="grid grid-cols-[1fr_auto_8rem] items-center gap-3 text-sm">
                  <span>{branch.label}</span>
                  <label className="flex items-center gap-2"><Checkbox checked={row.is_available} onCheckedChange={(checked) => setOverride(Number(branch.value), { is_available: checked === true })} />{f('available')}</label>
                  <Input dir="ltr" inputMode="decimal" placeholder={form.data.price || f('override_price')} aria-label={f('override_price')} value={row.price ?? ''} onChange={(event) => setOverride(Number(branch.value), { price: event.target.value || null })} />
                </div>
              );
            })}
          </fieldset>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function CatalogIndex({ companies, brands, branches, categories, groups, products }: Props) {
  const t = useT();
  const f = useField();
  const { locale } = useLocale();
  const { can } = useAccess();
  const canEdit = can('pos.catalog.create', 'pos.catalog.update');
  const companyName = (id: number) => (companies.length > 1 ? companies.find((company) => company.value === id)?.label : null);

  return (
    <SuiteLayout title={t('pos.catalog.title')}>
      <PageHeader title={t('pos.catalog.title')} />
      <Tabs defaultValue="products">
        <TabsList>
          <TabsTrigger value="products">{t('pos.catalog.products')}</TabsTrigger>
          <TabsTrigger value="categories">{t('pos.catalog.categories')}</TabsTrigger>
          <TabsTrigger value="groups">{t('pos.catalog.modifiers')}</TabsTrigger>
        </TabsList>

        <TabsContent value="products" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><ProductDialog companies={companies} categories={categories} groups={groups} branches={branches} /></div>}
          <DataTable<Product>
            rows={products}
            empty={t('pos.catalog.none')}
            columns={[
              { key: 'name', label: f('name_ar'), render: (product) => <span className={product.is_active ? undefined : 'text-muted-foreground line-through'}><span className="block font-medium">{product.name_ar}</span><bdi className="text-xs text-muted-foreground">{product.name_en}</bdi></span> },
              { key: 'category', label: f('category'), render: (product) => <span>{product.category ?? '—'} {companyName(product.company_id) && <span className="block text-xs text-muted-foreground">{companyName(product.company_id)}</span>}</span> },
              { key: 'price', label: f('price'), render: (product) => <span className="tabular-nums">{formatMoney(product.price, locale)}</span> },
              { key: 'calories', label: f('calories'), render: (product) => product.calories ?? '—' },
              { key: 'actions', label: '', className: 'text-end', render: (product) => canEdit && <ProductDialog product={product} companies={companies} categories={categories} groups={groups} branches={branches} /> },
            ]}
          />
        </TabsContent>

        <TabsContent value="categories" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><CategoryDialog companies={companies} brands={brands} /></div>}
          <DataTable<Category>
            rows={categories}
            empty={t('pos.catalog.none')}
            columns={[
              { key: 'name', label: f('name_ar'), render: (category) => <span className="flex items-center gap-2">{category.color && <span className="size-3 rounded-full" style={{ backgroundColor: category.color }} />}<span><span className="block font-medium">{category.name_ar}</span><bdi className="text-xs text-muted-foreground">{category.name_en}</bdi></span></span> },
              { key: 'brand', label: f('brand'), render: (category) => brands.find((brand) => brand.value === category.brand_id)?.label ?? '—' },
              { key: 'actions', label: '', className: 'text-end', render: (category) => canEdit && <CategoryDialog category={category} companies={companies} brands={brands} /> },
            ]}
          />
        </TabsContent>

        <TabsContent value="groups" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><GroupDialog companies={companies} /></div>}
          <DataTable<Group>
            rows={groups}
            empty={t('pos.catalog.none')}
            columns={[
              { key: 'name', label: f('name_ar'), render: (group) => <span><span className="block font-medium">{group.name}</span><span className="text-xs text-muted-foreground">{group.modifiers.map((modifier) => modifier.name).join('، ')}</span></span> },
              { key: 'range', label: `${f('min')} – ${f('max')}`, render: (group) => <span dir="ltr">{group.min} – {group.max}</span> },
              { key: 'actions', label: '', className: 'text-end', render: (group) => canEdit && <GroupDialog group={group} companies={companies} /> },
            ]}
          />
        </TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
