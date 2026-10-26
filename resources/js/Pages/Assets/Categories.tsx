import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SwitchField from '@/components/platform/SwitchField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';

interface Category { id: number; name: string; name_ar: string; name_en: string; useful_life_months: number; depreciates: boolean; is_active: boolean; [key: string]: unknown }

function CategoryDialog({ category }: { category?: Category }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.categories.fields.${field}`);
  const form = useForm({ name_ar: category?.name_ar ?? '', name_en: category?.name_en ?? '', useful_life_months: category?.useful_life_months ?? 60, depreciates: category?.depreciates ?? true, is_active: category?.is_active ?? true });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    category ? form.put(url(`/assets/categories/${category.id}`), options) : form.post(url('/assets/categories'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{category ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('assets.categories.new')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{category ? category.name : t('assets.categories.new')}</DialogTitle></DialogHeader>
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('useful_life_months')} error={form.errors.useful_life_months}><Input dir="ltr" type="number" min={1} value={form.data.useful_life_months} onChange={(event) => form.setData('useful_life_months', Number(event.target.value))} /></FormField>
          <div className="flex flex-col justify-end gap-2">
            <SwitchField id="category-depreciates" label={f('depreciates')} checked={form.data.depreciates} onChange={(value: boolean) => form.setData('depreciates', value)} />
            <SwitchField id="category-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
          </div>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Categories({ categories }: { categories: Category[] }) {
  const t = useT();
  const { can } = useAccess();
  const canEdit = can('assets.register.create', 'assets.register.update');
  const f = (field: string) => t(`assets.categories.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.categories.title')}>
      <PageHeader title={t('assets.categories.title')} actions={canEdit && <CategoryDialog />} />
      <DataTable<Category>
        rows={categories}
        empty={t('assets.categories.none')}
        columns={[
          { key: 'name', label: f('name_ar'), render: (category) => <span className={category.is_active ? undefined : 'text-muted-foreground line-through'}><span className="block font-medium">{category.name_ar}</span><bdi className="text-xs text-muted-foreground">{category.name_en}</bdi></span> },
          { key: 'life', label: f('useful_life_months'), render: (category) => t('assets.register.months', { count: category.useful_life_months }) },
          { key: 'depreciates', label: f('depreciates'), render: (category) => (category.depreciates ? '✓' : '—') },
          { key: 'actions', label: '', className: 'text-end', render: (category) => canEdit && <CategoryDialog category={category} /> },
        ]}
      />
    </SuiteLayout>
  );
}
