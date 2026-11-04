import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

export default function ClientForm({ client, statuses }) {
  const t = useT();
  const f = (field) => t(`admin.clients.fields.${field}`);
  const form = useForm({
    name_ar: client?.name_ar ?? '',
    name_en: client?.name_en ?? '',
    contact_name: client?.contact_name ?? '',
    contact_email: client?.contact_email ?? '',
    contact_phone: client?.contact_phone ?? '',
    status: client?.status ?? 'active',
    notes: client?.notes ?? '',
  });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    client ? form.put(`/admin/clients/${client.id}`) : form.post('/admin/clients');
  }

  const text = (field, props = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <Input id={field} value={data[field]} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );

  return (
    <AdminLayout title={client ? t('admin.clients.edit') : t('admin.clients.new')}>
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {text('name_ar', { dir: 'rtl' })}
            {text('name_en', { dir: 'ltr' })}
            {text('contact_name')}
            {text('contact_email', { type: 'email', dir: 'ltr' })}
            {text('contact_phone', { dir: 'ltr' })}
            <FormField label={f('status')} htmlFor="status" error={errors.status}>
              <SelectInput id="status" value={data.status} onChange={(value) => setData('status', value)} options={statuses} />
            </FormField>
            <FormField label={f('notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
              <Textarea id="notes" value={data.notes} onChange={(event) => setData('notes', event.target.value)} rows={3} />
            </FormField>
            <div className="sm:col-span-2">
              <FormActions processing={processing} cancelHref={client ? `/admin/clients/${client.id}` : '/admin/clients'} />
            </div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
