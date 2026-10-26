import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SwitchField from '@/components/platform/SwitchField';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

export default function ProductForm({ product }) {
  const t = useT();
  const f = (field) => t(`admin.products.fields.${field}`);
  const form = useForm({
    slug: product?.slug ?? '',
    name_ar: product?.name_ar ?? '',
    name_en: product?.name_en ?? '',
    description_ar: product?.description_ar ?? '',
    description_en: product?.description_en ?? '',
    url: product?.url ?? '',
    is_active: product?.is_active ?? true,
    sort_order: product?.sort_order ?? 0,
    logo: null,
  });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    // Multipart for the logo; updates spoof PUT because files can't travel in a real PUT.
    if (product) {
      form.transform((values) => ({ ...values, _method: 'put' }));
      form.post(`/admin/products/${product.id}`, { forceFormData: true });
    } else {
      form.post('/admin/products', { forceFormData: true });
    }
  }

  const input = (field, { hint, ...props } = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]} hint={hint}>
      <Input id={field} value={data[field] ?? ''} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );

  return (
    <AdminLayout title={product ? t('admin.products.edit') : t('admin.products.new')}>
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {input('name_ar', { dir: 'rtl' })}
            {input('name_en', { dir: 'ltr' })}
            {input('slug', { dir: 'ltr', hint: t('admin.projects.fields.slug_hint') })}
            {input('url', { type: 'url', dir: 'ltr' })}
            {input('sort_order', { type: 'number', min: 0, dir: 'ltr' })}
            <div className="flex items-end pb-2">
              <SwitchField id="is_active" label={f('is_active')} checked={data.is_active} onChange={(value) => setData('is_active', value)} />
            </div>
            <FormField label={f('logo')} htmlFor="logo" error={errors.logo} hint={t('admin.projects.fields.logo_hint')}>
              <Input id="logo" type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => setData('logo', event.target.files[0] ?? null)} />
            </FormField>
            {product?.logo_url && <img src={product.logo_url} alt="" className="size-16 rounded-md border object-contain" />}
            <FormField label={f('description_ar')} htmlFor="description_ar" error={errors.description_ar} className="sm:col-span-2">
              <Textarea id="description_ar" dir="rtl" rows={3} value={data.description_ar} onChange={(event) => setData('description_ar', event.target.value)} />
            </FormField>
            <FormField label={f('description_en')} htmlFor="description_en" error={errors.description_en} className="sm:col-span-2">
              <Textarea id="description_en" dir="ltr" rows={3} value={data.description_en} onChange={(event) => setData('description_en', event.target.value)} />
            </FormField>
            <div className="sm:col-span-2"><FormActions processing={processing} cancelHref="/admin/products" /></div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
