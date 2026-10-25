import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

function Checks({ options, value, onChange }) {
  const toggle = (key, on) => onChange(on ? [...value, key] : value.filter((item) => item !== key));

  return (
    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      {options.map((option) => (
        <label key={option.value} className="flex items-center gap-2 text-sm">
          <Checkbox checked={value.includes(option.value)} onCheckedChange={(state) => toggle(option.value, state === true)} />{option.label}
        </label>
      ))}
    </div>
  );
}

/**
 * A package (or add-on): modules, web features, employee-app screens and limits. Limits left
 * empty are unlimited; on an add-on they are added on top of the package per quantity bought.
 */
export default function PackageForm({ kind, item, catalog }) {
  const t = useT();
  const p = (field) => t(`admin.packages.${field}`);
  const isAddon = kind === 'addon';
  const form = useForm({
    code: item?.code ?? '', name_ar: item?.name_ar ?? '', name_en: item?.name_en ?? '',
    description_ar: item?.description_ar ?? '', description_en: item?.description_en ?? '',
    price_monthly: item?.price_monthly ?? '0', price_yearly: item?.price_yearly ?? '0', trial_days: String(item?.trial_days ?? 0),
    modules: item?.modules ?? [], features: item?.features ?? [], mobile: item?.mobile ?? [],
    limits: Object.fromEntries(catalog.limits.map((limit) => [limit.value, item?.limits?.[limit.value] ?? ''])),
    is_public: item?.is_public ?? false, is_active: item?.is_active ?? true, sort_order: String(item?.sort_order ?? 0),
  });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    form.transform((values) => ({ ...values, limits: Object.fromEntries(Object.entries(values.limits).map(([key, value]) => [key, value === '' ? null : Number(value)])) }));
    item ? form.put(`/admin/packages/${kind}/${item.id}`) : form.post(`/admin/packages/${kind}`);
  }

  const text = (field, props = {}) => (
    <FormField label={p(`fields.${field}`)} htmlFor={field} error={errors[field]}>
      <Input id={field} value={data[field]} onChange={(event) => setData(field, event.target.value)} {...props} />
    </FormField>
  );

  return (
    <AdminLayout title={p(isAddon ? 'addon' : 'package')}>
      <PageHeader title={item ? `${p(isAddon ? 'addon' : 'package')} · ${item.name_en}` : p(isAddon ? 'new_addon' : 'new_package')} />
      <form onSubmit={submit} className="flex max-w-4xl flex-col gap-4">
        <Card>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            {text('code', { dir: 'ltr' })}
            {text('price_monthly', { dir: 'ltr', type: 'number', step: '0.01', min: '0' })}
            {text('name_ar', { dir: 'rtl' })}
            {text('name_en', { dir: 'ltr' })}
            {!isAddon && (
              <>
                {text('price_yearly', { dir: 'ltr', type: 'number', step: '0.01', min: '0' })}
                {text('trial_days', { dir: 'ltr', type: 'number', min: '0' })}
                <FormField label={p('fields.description_ar')} error={errors.description_ar}><Textarea rows={2} dir="rtl" value={data.description_ar} onChange={(event) => setData('description_ar', event.target.value)} /></FormField>
                <FormField label={p('fields.description_en')} error={errors.description_en}><Textarea rows={2} dir="ltr" value={data.description_en} onChange={(event) => setData('description_en', event.target.value)} /></FormField>
                <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.is_public} onCheckedChange={(state) => setData('is_public', state === true)} />{p('fields.is_public')}</label>
              </>
            )}
            <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.is_active} onCheckedChange={(state) => setData('is_active', state === true)} />{p('fields.is_active')}</label>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{p('modules')}</CardTitle></CardHeader>
          <CardContent><Checks options={catalog.modules} value={data.modules} onChange={(value) => setData('modules', value)} /></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{p('limits')}</CardTitle></CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <p className="text-sm text-muted-foreground sm:col-span-3">{p(isAddon ? 'limits_addon_hint' : 'limits_hint')}</p>
            {catalog.limits.map((limit) => (
              <FormField key={limit.value} label={limit.label} error={errors[`limits.${limit.value}`]}>
                <Input type="number" min="0" dir="ltr" placeholder="∞" value={data.limits[limit.value]} onChange={(event) => setData('limits', { ...data.limits, [limit.value]: event.target.value })} />
              </FormField>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{p('features')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-4">
            {Object.entries(catalog.features).map(([area, options]) => (
              <div key={area} className="flex flex-col gap-2">
                <span className="text-xs font-medium text-muted-foreground">{t(`suite.catalog.area_${area}`)}</span>
                <Checks options={options} value={data.features} onChange={(value) => setData('features', value)} />
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{p('mobile')}</CardTitle></CardHeader>
          <CardContent><Checks options={catalog.mobile} value={data.mobile} onChange={(value) => setData('mobile', value)} /></CardContent>
        </Card>
        <FormActions processing={processing} />
      </form>
    </AdminLayout>
  );
}
