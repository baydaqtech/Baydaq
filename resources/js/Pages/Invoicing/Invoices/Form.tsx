import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface Line { description: string; quantity: string; unit_price: string; discount: string; vat_rate: string }
interface Draft { id: number; company_id: number; customer_id: number | null; type: string; kind: string; related_invoice_id: number | null; issue_date: string; due_date: string | null; supply_date: string | null; payment_means: string; note: string | null; reason: string | null; lines: Line[] }
interface Props {
  invoice: Draft | null;
  companies: (Option & { ready: boolean })[];
  customers: (Option & { vat: boolean })[];
  issued: (Option & { company_id: number })[];
  types: Option[];
  kinds: Option[];
  payment_means: Option[];
}

const blank: Line = { description: '', quantity: '1', unit_price: '', discount: '0', vat_rate: '15' };

export default function InvoiceForm({ invoice, companies, customers, issued, types, kinds, payment_means: paymentMeans }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const f = (field: string) => t(`invoicing.invoices.fields.${field}`);
  const form = useForm<{ company_id: number | ''; customer_id: number | ''; type: string; kind: string; related_invoice_id: number | ''; issue_date: string; due_date: string; supply_date: string; payment_means: string; note: string; reason: string; lines: Line[] }>({
    company_id: invoice?.company_id ?? (companies.find((company) => company.ready)?.value as number) ?? '',
    customer_id: invoice?.customer_id ?? '',
    type: invoice?.type ?? 'simplified',
    kind: invoice?.kind ?? 'invoice',
    related_invoice_id: invoice?.related_invoice_id ?? '',
    issue_date: invoice?.issue_date ?? new Date().toISOString().slice(0, 10),
    due_date: invoice?.due_date ?? '',
    supply_date: invoice?.supply_date ?? '',
    payment_means: invoice?.payment_means ?? '10',
    note: invoice?.note ?? '',
    reason: invoice?.reason ?? '',
    lines: invoice?.lines ?? [{ ...blank }],
  });
  const { data, setData, errors, processing } = form;
  const update = (index: number, patch: Partial<Line>) => setData('lines', data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));

  const totals = data.lines.reduce((sum, line) => {
    const net = Number(line.quantity || 0) * Number(line.unit_price || 0) - Number(line.discount || 0);
    const vat = Math.round(net * Number(line.vat_rate || 0)) / 100;
    return { net: sum.net + net, vat: sum.vat + vat };
  }, { net: 0, vat: 0 });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    invoice ? form.put(url(`/invoicing/invoices/${invoice.id}`)) : form.post(url('/invoicing/invoices'));
  }

  return (
    <SuiteLayout title={invoice ? t('invoicing.invoices.edit') : t('invoicing.invoices.new')}>
      <form onSubmit={submit} className="flex max-w-5xl flex-col gap-4">
        <Card>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            {!invoice && <FormField label={t('invoicing.units.fields.company')} error={errors.company_id}><SelectInput value={data.company_id} onChange={(value) => setData('company_id', Number(value))} options={companies} /></FormField>}
            <FormField label={f('type')} error={errors.type}><SelectInput value={data.type} onChange={(value) => setData('type', value)} options={types} /></FormField>
            <FormField label={f('kind')} error={errors.kind}><SelectInput value={data.kind} onChange={(value) => setData('kind', value)} options={kinds} /></FormField>
            <FormField label={f('customer')} error={errors.customer_id} hint={data.type === 'standard' ? t('invoicing.errors.standard_needs_customer') : undefined}>
              <SelectInput value={data.customer_id} onChange={(value) => setData('customer_id', value === '' ? '' : Number(value))} options={customers.filter((customer) => data.type !== 'standard' || customer.vat)} emptyLabel={t('invoicing.invoices.customer_walkin')} />
            </FormField>
            {data.kind !== 'invoice' && (
              <>
                <FormField label={f('related')} error={errors.related_invoice_id}><SelectInput value={data.related_invoice_id} onChange={(value) => setData('related_invoice_id', Number(value))} options={issued.filter((item) => item.company_id === Number(data.company_id))} /></FormField>
                <FormField label={f('reason')} error={errors.reason}><Input value={data.reason} onChange={(event) => setData('reason', event.target.value)} /></FormField>
              </>
            )}
            <FormField label={f('issue_date')} error={errors.issue_date}><Input type="date" dir="ltr" value={data.issue_date} onChange={(event) => setData('issue_date', event.target.value)} /></FormField>
            <FormField label={f('due_date')} error={errors.due_date}><Input type="date" dir="ltr" value={data.due_date} onChange={(event) => setData('due_date', event.target.value)} /></FormField>
            <FormField label={f('supply_date')} error={errors.supply_date}><Input type="date" dir="ltr" value={data.supply_date} onChange={(event) => setData('supply_date', event.target.value)} /></FormField>
            <FormField label={f('payment_means')} error={errors.payment_means}><SelectInput value={data.payment_means} onChange={(value) => setData('payment_means', value)} options={paymentMeans} /></FormField>
            <FormField label={f('note')} error={errors.note} className="sm:col-span-2"><Input value={data.note} onChange={(event) => setData('note', event.target.value)} /></FormField>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between"><CardTitle className="text-base">{t('invoicing.invoices.lines')}</CardTitle><Button type="button" size="xs" variant="outline" onClick={() => setData('lines', [...data.lines, { ...blank }])}><Plus data-icon="inline-start" />{t('invoicing.invoices.add_line')}</Button></CardHeader>
          <CardContent className="flex flex-col gap-2">
            {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
            {data.lines.map((line, index) => (
              <div key={index} className="grid gap-2 sm:grid-cols-[2fr_80px_120px_100px_90px_auto]">
                <Input placeholder={f('description')} value={line.description} onChange={(event) => update(index, { description: event.target.value })} aria-invalid={!!(errors as Record<string, string>)[`lines.${index}.description`]} />
                <Input type="number" step="0.001" dir="ltr" placeholder={f('quantity')} value={line.quantity} onChange={(event) => update(index, { quantity: event.target.value })} />
                <Input type="number" step="0.0001" dir="ltr" placeholder={f('unit_price')} value={line.unit_price} onChange={(event) => update(index, { unit_price: event.target.value })} />
                <Input type="number" step="0.01" dir="ltr" placeholder={f('discount')} value={line.discount} onChange={(event) => update(index, { discount: event.target.value })} />
                <SelectInput value={line.vat_rate} onChange={(value) => update(index, { vat_rate: value })} options={[{ value: '15', label: '15%' }, { value: '5', label: '5%' }, { value: '0', label: '0%' }]} />
                <Button type="button" variant="ghost" size="icon-sm" onClick={() => setData('lines', data.lines.filter((_, i) => i !== index))} aria-label={t('common.actions.remove')}><Trash2 /></Button>
              </div>
            ))}
            <div className="mt-2 flex flex-wrap justify-end gap-6 text-sm">
              <span>{f('subtotal')}: <strong>{formatMoney(totals.net.toFixed(2), locale)}</strong></span>
              <span>{f('vat')}: <strong>{formatMoney(totals.vat.toFixed(2), locale)}</strong></span>
              <span>{f('total')}: <strong>{formatMoney((totals.net + totals.vat).toFixed(2), locale)}</strong></span>
            </div>
          </CardContent>
        </Card>
        <FormActions processing={processing} cancelHref={invoice ? url(`/invoicing/invoices/${invoice.id}`) : url('/invoicing/invoices')} />
      </form>
    </SuiteLayout>
  );
}
