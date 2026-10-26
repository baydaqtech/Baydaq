import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Tags } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import SwitchField from '@/components/platform/SwitchField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';
import type { Option } from '@/types/resources';

interface Brand {
  id: number;
  company_id: number;
  slug: string;
  name: string;
  name_ar: string;
  name_en: string;
  color: string | null;
  is_active: boolean;
  branch_ids: number[];
}

function BrandDialog({ brand, companies, branches }: { brand?: Brand; companies: Option[]; branches: ScopedOption[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({
    company_id: brand?.company_id ?? companies[0]?.value ?? '',
    name_ar: brand?.name_ar ?? '',
    name_en: brand?.name_en ?? '',
    slug: brand?.slug ?? '',
    color: brand?.color ?? '#c8a84b',
    is_active: brand?.is_active ?? true,
    branch_ids: brand?.branch_ids ?? ([] as number[]),
  });
  const { data, setData, errors } = form;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((payload) => ({ ...payload, slug: payload.slug || null }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    brand ? form.put(url(`/structure/brands/${brand.id}`), options) : form.post(url('/structure/brands'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {brand ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('suite.structure.new_brand')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2">
            <DialogTitle>{brand ? brand.name : t('suite.structure.new_brand')}</DialogTitle>
            <DialogDescription>{t('suite.structure.brand_hint')}</DialogDescription>
          </DialogHeader>
          {!brand && (
            <FormField className="sm:col-span-2" label={t('suite.structure.fields.company')} error={errors.company_id}>
              <SelectInput value={data.company_id} onChange={(value) => setData((current) => ({ ...current, company_id: Number(value), branch_ids: [] }))} options={companies} />
            </FormField>
          )}
          <FormField label={t('suite.structure.fields.name_ar')} error={errors.name_ar}><Input value={data.name_ar} onChange={(event) => setData('name_ar', event.target.value)} /></FormField>
          <FormField label={t('suite.structure.fields.name_en')} error={errors.name_en}><Input dir="ltr" value={data.name_en} onChange={(event) => setData('name_en', event.target.value)} /></FormField>
          <FormField label={t('suite.structure.fields.slug')} error={errors.slug}><Input dir="ltr" value={data.slug} onChange={(event) => setData('slug', event.target.value)} /></FormField>
          <FormField label={t('suite.structure.brand_fields.color')} error={errors.color}><Input type="color" value={data.color ?? '#c8a84b'} onChange={(event) => setData('color', event.target.value)} /></FormField>
          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{t('suite.structure.brand_fields.branches')}</legend>
            <div className="flex flex-wrap gap-3">
              {branches.filter((branch) => branch.company_id === Number(data.company_id)).map((branch) => (
                <label key={branch.value} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={data.branch_ids.includes(Number(branch.value))} onCheckedChange={(checked) => setData('branch_ids', checked ? [...data.branch_ids, Number(branch.value)] : data.branch_ids.filter((id) => id !== Number(branch.value)))} />
                  {branch.label}
                </label>
              ))}
            </div>
            {(errors.branch_ids || errors['branch_ids.0']) && <p className="text-sm text-destructive">{errors.branch_ids ?? errors['branch_ids.0']}</p>}
          </fieldset>
          <SwitchField id="brand-active" label={t('suite.structure.status_active')} checked={data.is_active} onChange={(value: boolean) => setData('is_active', value)} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Brands({ brands, companies, branches }: { brands: Brand[]; companies: Option[]; branches: ScopedOption[] }) {
  const t = useT();
  const { can } = useAccess();
  const branchName = (id: number) => branches.find((branch) => branch.value === id)?.label;

  return (
    <SuiteLayout title={t('suite.structure.brands')}>
      <PageHeader title={t('suite.structure.brands')} description={t('suite.structure.brand_hint')} actions={can('suite.structure.create') && companies.length > 0 && <BrandDialog companies={companies} branches={branches} />} />
      {brands.length === 0 ? <StatusMessage>{t('suite.structure.brands_empty')}</StatusMessage> : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {brands.map((brand) => (
            <Card key={brand.id} className={brand.is_active ? undefined : 'opacity-60'}>
              <CardContent className="flex items-start gap-3">
                <span className="mt-1 flex size-8 items-center justify-center rounded-md text-white" style={{ backgroundColor: brand.color ?? '#7a5c3e' }}><Tags className="size-4" /></span>
                <div className="flex-1">
                  <p className="font-medium">{brand.name_ar} <bdi className="text-xs text-muted-foreground">{brand.name_en}</bdi></p>
                  <div className="mt-2 flex flex-wrap gap-1">{brand.branch_ids.map((id) => <Badge key={id} variant="secondary">{branchName(id)}</Badge>)}</div>
                </div>
                {can('suite.structure.update') && <BrandDialog brand={brand} companies={companies} branches={branches} />}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </SuiteLayout>
  );
}
