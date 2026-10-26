import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import Repeater from '@/components/platform/Repeater';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

// Same rounding as the server (SaveInvoice): line totals and VAT to 2 decimals, half up.
const cents = (value) => Math.round((Number(value) || 0) * 100);
const round2 = (value) => Math.round(value * 100) / 100;

export default function InvoiceForm({ invoice, defaults, options }) {
  const t = useT();
  const { locale } = useLocale();
  const f = (field) => t(`admin.invoices.fields.${field}`);
  const form = useForm({
    client_id: invoice?.client_id ?? defaults?.client_id ?? '',
    project_id: invoice?.project_id ?? defaults?.project_id ?? '',
    issue_date: invoice?.issue_date ?? defaults?.issue_date ?? '',
    due_date: invoice?.due_date ?? defaults?.due_date ?? '',
    notes: invoice?.notes ?? '',
    items: invoice?.items?.map(({ description, quantity, unit_price }) => ({ description, quantity, unit_price }))
      ?? [{ description: '', quantity: 1, unit_price: '' }],
  });
  const { data, setData, errors, processing } = form;

  const projects = options.projects.filter((project) => String(project.client_id) === String(data.client_id));
  const subtotal = data.items.reduce((sum, item) => sum + round2((Number(item.quantity) || 0) * cents(item.unit_price) / 100), 0);
  const vatRate = Number(invoice?.vat_rate ?? options.vatRate);
  const vat = round2(subtotal * vatRate);
  const ratePercent = Number((vatRate * 100).toFixed(2));

  function submit(event) {
    event.preventDefault();
    invoice ? form.put(`/admin/invoices/${invoice.id}`) : form.post('/admin/invoices');
  }

  const itemError = (index, field) => errors[`items.${index}.${field}`];

  return (
    <AdminLayout title={invoice ? `${t('admin.invoices.edit')} ${invoice.number}` : t('admin.invoices.new')}>
      <form onSubmit={submit} className="grid max-w-4xl gap-4">
        <Card>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <FormField label={f('client')} htmlFor="client_id" error={errors.client_id}>
              <SelectInput id="client_id" value={data.client_id} onChange={(value) => setData({ ...data, client_id: value, project_id: '' })} options={options.clients} />
            </FormField>
            <FormField label={f('project')} htmlFor="project_id" error={errors.project_id}>
              <SelectInput id="project_id" value={data.project_id} onChange={(value) => setData('project_id', value)} options={projects} emptyLabel={f('no_project')} />
            </FormField>
            <FormField label={f('issue_date')} htmlFor="issue_date" error={errors.issue_date}>
              <Input id="issue_date" type="date" value={data.issue_date} onChange={(event) => setData('issue_date', event.target.value)} />
            </FormField>
            <FormField label={f('due_date')} htmlFor="due_date" error={errors.due_date}>
              <Input id="due_date" type="date" value={data.due_date} onChange={(event) => setData('due_date', event.target.value)} />
            </FormField>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="flex flex-col gap-4">
            <h3 className="font-medium">{f('items')}</h3>
            {errors.items && <p className="text-sm text-destructive">{errors.items}</p>}
            <Repeater
              items={data.items}
              onChange={(items) => setData('items', items)}
              blank={{ description: '', quantity: 1, unit_price: '' }}
              minRows={1}
              addLabel={t('admin.invoices.add_item')}
              renderRow={(item, update, index) => (
                <div className="grid gap-3 sm:grid-cols-[1fr_6rem_9rem_8rem]">
                  <FormField label={f('description')} error={itemError(index, 'description')}>
                    <Input value={item.description} onChange={(event) => update('description', event.target.value)} />
                  </FormField>
                  <FormField label={f('quantity')} error={itemError(index, 'quantity')}>
                    <Input type="number" min="0" step="0.01" dir="ltr" value={item.quantity} onChange={(event) => update('quantity', event.target.value)} />
                  </FormField>
                  <FormField label={f('unit_price')} error={itemError(index, 'unit_price')}>
                    <Input type="number" min="0" step="0.01" dir="ltr" value={item.unit_price} onChange={(event) => update('unit_price', event.target.value)} />
                  </FormField>
                  <FormField label={f('line_total')}>
                    <div className="flex h-8 items-center text-sm">{formatMoney(round2((Number(item.quantity) || 0) * cents(item.unit_price) / 100), locale)}</div>
                  </FormField>
                </div>
              )}
            />
            <dl className="ms-auto grid w-full max-w-xs gap-1 text-sm">
              <div className="flex justify-between"><dt className="text-muted-foreground">{t('admin.invoices.subtotal')}</dt><dd>{formatMoney(subtotal, locale)}</dd></div>
              <div className="flex justify-between"><dt className="text-muted-foreground">{t('admin.invoices.vat', { rate: ratePercent })}</dt><dd>{formatMoney(vat, locale)}</dd></div>
              <div className="flex justify-between border-t pt-1 font-semibold"><dt>{t('admin.invoices.total')}</dt><dd>{formatMoney(subtotal + vat, locale)}</dd></div>
            </dl>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="flex flex-col gap-4">
            <FormField label={f('notes')} htmlFor="notes" error={errors.notes}>
              <Textarea id="notes" rows={3} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
            </FormField>
            <FormActions processing={processing} cancelHref={invoice ? `/admin/invoices/${invoice.id}` : '/admin/invoices'} />
          </CardContent>
        </Card>
      </form>
    </AdminLayout>
  );
}
