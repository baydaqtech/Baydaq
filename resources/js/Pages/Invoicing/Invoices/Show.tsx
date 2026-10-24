import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Download, FileText, Pencil, Send } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';
import type { InvoiceSummary } from './Index';

interface Line { id: number; description: string; quantity: string; unit_price: string; discount: string; vat_rate: string; net: string; vat: string; total: string }
interface Submission { id: number; mode: string; environment: string; status: string; counter: number; submitted_at: string; response: { http: number; status: string; body: Record<string, unknown> } | null }
type Full = InvoiceSummary & { rounding: string; payable: string; uuid: string; due_date: string | null; supply_date: string | null; payment_means: string; note: string | null; reason: string | null; related: string | null; discount: string; qr: string | null; hash: string | null; counter: number | null; unit: string | null; issued_at: string | null; lines: Line[]; submissions: Submission[] };

function IssueDialog({ invoice, units }: { invoice: Full; units: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ unit_id: units[0]?.value ?? '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" disabled={units.length === 0}><Send data-icon="inline-start" />{t('invoicing.invoices.issue')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/invoicing/invoices/${invoice.id}/issue`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('invoicing.invoices.issue_title')}</DialogTitle><DialogDescription>{t('invoicing.invoices.issue_text')}</DialogDescription></DialogHeader>
          <FormField label={t('invoicing.invoices.fields.unit')} error={form.errors.unit_id}><SelectInput value={form.data.unit_id} onChange={(value) => form.setData('unit_id', Number(value))} options={units} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('invoicing.invoices.issue')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function InvoiceShow({ invoice, units, can }: { invoice: Full; units: Option[]; can: { edit: boolean; issue: boolean; cancel: boolean; export: boolean } }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const f = (field: string) => t(`invoicing.invoices.fields.${field}`);
  const money = (value: string) => formatMoney(value, locale);

  return (
    <SuiteLayout title={invoice.number ?? t('invoicing.invoices.title')}>
      <PageHeader
        title={<span className="flex items-center gap-2"><FileText className="size-5 text-brass" /><span dir="ltr">{invoice.number ?? '—'}</span><StatusBadge group="invoice_status" value={invoice.status} file="invoicing" /></span>}
        description={`${t(`invoicing.invoice_kind.${invoice.kind}`)} · ${t(`invoicing.invoice_type.${invoice.type}`)} · ${invoice.company ?? ''}`}
        actions={(
          <>
            {can.edit && <Button asChild size="sm" variant="outline"><Link href={url(`/invoicing/invoices/${invoice.id}/edit`)}><Pencil data-icon="inline-start" />{t('common.actions.edit')}</Link></Button>}
            {can.issue && <IssueDialog invoice={invoice} units={units} />}
            {can.cancel && <ConfirmAction href={url(`/invoicing/invoices/${invoice.id}/cancel`)} method="post" size="sm" variant="ghost" title={t('invoicing.invoices.cancel')}>{t('invoicing.invoices.cancel')}</ConfirmAction>}
            <Button asChild size="sm" variant="outline"><a href={url(`/invoicing/invoices/${invoice.id}/pdf`)} target="_blank" rel="noreferrer"><Download data-icon="inline-start" />{t('invoicing.invoices.download_pdf')}</a></Button>
            {can.export && <Button asChild size="sm" variant="outline"><a href={url(`/invoicing/invoices/${invoice.id}/xml`)}><Download data-icon="inline-start" />{t('invoicing.invoices.download_xml')}</a></Button>}
          </>
        )}
      />
      {can.issue && units.length === 0 && <p className="mb-4 text-sm text-amber-700">{t('invoicing.errors.unit_not_ready')}</p>}

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2"><CardContent>
          <DetailList items={[
            { label: f('customer'), value: invoice.customer ?? t('invoicing.invoices.customer_walkin') },
            { label: f('issue_date'), value: formatDate(invoice.issue_date, locale) },
            { label: f('due_date'), value: formatDate(invoice.due_date, locale) },
            { label: f('supply_date'), value: formatDate(invoice.supply_date, locale) },
            { label: f('payment_means'), value: t(`invoicing.invoices.payment.${invoice.payment_means}`) },
            invoice.related ? { label: f('related'), value: <span dir="ltr">{invoice.related}</span> } : null,
            invoice.reason ? { label: f('reason'), value: invoice.reason } : null,
            { label: f('note'), value: invoice.note, wide: true },
            invoice.unit ? { label: f('unit'), value: `${invoice.unit} · #${invoice.counter}` } : null,
            invoice.issued_at ? { label: f('issued_at'), value: formatDate(invoice.issued_at, locale) } : null,
          ]} />
        </CardContent></Card>
        <Card><CardHeader><CardTitle className="text-base">{t('invoicing.invoices.totals')}</CardTitle></CardHeader><CardContent>
          <DetailList className="sm:grid-cols-1" items={[
            { label: f('subtotal'), value: money(invoice.subtotal) },
            { label: f('discount'), value: money(invoice.discount) },
            { label: f('vat'), value: money(invoice.vat) },
            ...(Number(invoice.rounding) !== 0 ? [{ label: t('invoicing.pdf.rounding'), value: money(invoice.rounding) }] : []),
            { label: f('total'), value: <strong>{money(invoice.payable)}</strong> },
          ]} />
          {invoice.qr && <div className="mt-4"><div className="mb-1 text-xs text-muted-foreground">{t('invoicing.invoices.qr')}</div><code className="block break-all rounded bg-muted p-2 text-[10px] leading-tight" dir="ltr">{invoice.qr}</code></div>}
        </CardContent></Card>
      </div>

      <Card className="mt-4 gap-0 overflow-hidden py-0"><Table>
        <TableHeader><TableRow className="bg-muted/50"><TableHead className="px-4 text-start">{f('description')}</TableHead><TableHead className="text-start">{f('quantity')}</TableHead><TableHead className="text-start">{f('unit_price')}</TableHead><TableHead className="text-start">{f('discount')}</TableHead><TableHead className="text-start">{f('vat')}</TableHead><TableHead className="px-4 text-start">{f('total')}</TableHead></TableRow></TableHeader>
        <TableBody>
          {invoice.lines.map((line) => (
            <TableRow key={line.id}><TableCell className="px-4">{line.description}</TableCell><TableCell dir="ltr">{line.quantity}</TableCell><TableCell>{money(line.unit_price)}</TableCell><TableCell>{money(line.discount)}</TableCell><TableCell>{money(line.vat)} <span className="text-xs text-muted-foreground">({line.vat_rate}%)</span></TableCell><TableCell className="px-4 font-medium">{money(line.total)}</TableCell></TableRow>
          ))}
        </TableBody>
      </Table></Card>

      {invoice.submissions.length > 0 && (
        <Card className="mt-4"><CardHeader><CardTitle className="text-base">{t('invoicing.invoices.submissions')}</CardTitle></CardHeader><CardContent>
          <ul className="divide-y text-sm">
            {invoice.submissions.map((submission) => (
              <li key={submission.id} className="py-2">
                <div className="flex flex-wrap items-center gap-2"><span className="font-medium">{submission.mode}</span><span className="text-muted-foreground">· {submission.environment} · #{submission.counter} · {formatDate(submission.submitted_at, locale)}</span><StatusBadge group="submission_status" value={submission.status === 'accepted' || submission.status === 'accepted_with_warnings' ? 'approved' : submission.status === 'rejected' ? 'rejected' : 'draft'} /></div>
                {submission.response && submission.status !== 'accepted' && <pre className="mt-1 max-h-40 overflow-auto rounded bg-muted p-2 text-[11px]" dir="ltr">{JSON.stringify(submission.response.body, null, 2)}</pre>}
              </li>
            ))}
          </ul>
        </CardContent></Card>
      )}
    </SuiteLayout>
  );
}
