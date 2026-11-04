import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

// Field order on the form, with the text direction each one is usually written in.
const FIELDS = [
  ['company_name_ar', 'rtl'], ['company_name_en', 'ltr'],
  ['vat_number', 'ltr'], ['cr_number', 'ltr'],
  ['address_ar', 'rtl'], ['address_en', 'ltr'],
  ['bank_name', null], ['account_name', null],
  ['iban', 'ltr'], ['invoice_note', null],
];

export default function Settings({ issuer, einvoicing_company_id: einvoicingCompanyId, companies }) {
  const t = useT();
  const form = useForm({ ...Object.fromEntries(FIELDS.map(([key]) => [key, issuer[key] ?? ''])), einvoicing_company_id: einvoicingCompanyId ?? '' });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    form.put('/admin/settings', { preserveScroll: true });
  }

  return (
    <AdminLayout title={t('admin.settings.title')}>
      <Card className="max-w-3xl">
        <CardContent>
          <PageHeader title={t('admin.settings.issuer')} description={t('admin.settings.issuer_text')} />
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {FIELDS.map(([key, dir]) => (
              <FormField key={key} label={t(`admin.settings.fields.${key}`)} htmlFor={key} error={errors[key]} className={key === 'invoice_note' ? 'sm:col-span-2' : undefined}>
                <Input id={key} dir={dir ?? undefined} value={data[key]} onChange={(event) => setData(key, event.target.value)} />
              </FormField>
            ))}
            <div className="flex flex-col gap-2 border-t pt-4 sm:col-span-2">
              <h3 className="text-sm font-semibold">{t('admin.settings.einvoicing')}</h3>
              <p className="text-xs text-muted-foreground">{t('admin.settings.einvoicing_text')}</p>
              <FormField label={t('admin.settings.fields.einvoicing_company_id')} htmlFor="einvoicing_company_id" error={errors.einvoicing_company_id}>
                <SelectInput id="einvoicing_company_id" value={data.einvoicing_company_id} onChange={(value) => setData('einvoicing_company_id', value)} options={companies} emptyLabel={t('admin.settings.einvoicing_off')} />
              </FormField>
            </div>
            <div className="sm:col-span-2"><FormActions processing={processing} /></div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
