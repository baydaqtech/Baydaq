import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';
import { SERVICE_ICON_NAMES, serviceIcon } from '@/lib/serviceIcons';

export default function ServiceForm({ service, pricingModels }) {
  const t = useT();
  const f = (field) => t(`admin.services.fields.${field}`);
  const form = useForm({
    key: service?.key ?? '',
    name_ar: service?.name_ar ?? '',
    name_en: service?.name_en ?? '',
    description_ar: service?.description_ar ?? '',
    description_en: service?.description_en ?? '',
    icon: service?.icon ?? '',
    is_active: service?.is_active ?? true,
    sort_order: service?.sort_order ?? 0,
    docs_url: service?.docs_url ?? '',
    default_price: service?.default_price ?? '',
    pricing_model: service?.pricing_model ?? 'monthly',
    unit: service?.unit ?? '',
  });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    service ? form.put(`/admin/services/${service.id}`) : form.post('/admin/services');
  }

  const input = (field, { hint, ...props } = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]} hint={hint}>
      <Input id={field} value={data[field] ?? ''} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );
  const Icon = serviceIcon(data.icon);

  return (
    <AdminLayout title={service ? t('admin.services.edit') : t('admin.services.new')}>
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {input('key', { dir: 'ltr', hint: f('key_hint') })}
            <FormField label={f('icon')} htmlFor="icon" error={errors.icon}>
              <div className="flex items-center gap-2">
                <Icon className="size-5 shrink-0 text-brass" />
                <SelectInput id="icon" value={data.icon} onChange={(value) => setData('icon', value)} options={SERVICE_ICON_NAMES.map((name) => ({ value: name, label: name }))} />
              </div>
            </FormField>
            {input('name_ar', { dir: 'rtl' })}
            {input('name_en', { dir: 'ltr' })}
            <FormField label={f('pricing_model')} htmlFor="pricing_model" error={errors.pricing_model}>
              <SelectInput id="pricing_model" value={data.pricing_model} onChange={(value) => setData('pricing_model', value)} options={pricingModels} />
            </FormField>
            {input('default_price', { type: 'number', min: 0, step: '0.01', dir: 'ltr' })}
            {input('unit', { dir: 'ltr', hint: f('unit_hint') })}
            {input('sort_order', { type: 'number', min: 0, dir: 'ltr' })}
            {input('docs_url', { type: 'url', dir: 'ltr' })}
            <div className="flex items-end pb-2">
              <SwitchField id="is_active" label={f('is_active')} checked={data.is_active} onChange={(value) => setData('is_active', value)} />
            </div>
            <FormField label={f('description_ar')} htmlFor="description_ar" error={errors.description_ar} className="sm:col-span-2">
              <Textarea id="description_ar" dir="rtl" rows={3} value={data.description_ar} onChange={(event) => setData('description_ar', event.target.value)} />
            </FormField>
            <FormField label={f('description_en')} htmlFor="description_en" error={errors.description_en} className="sm:col-span-2">
              <Textarea id="description_en" dir="ltr" rows={3} value={data.description_en} onChange={(event) => setData('description_en', event.target.value)} />
            </FormField>
            <div className="sm:col-span-2"><FormActions processing={processing} cancelHref="/admin/services" /></div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
