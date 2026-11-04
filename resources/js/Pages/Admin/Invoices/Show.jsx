import { Link } from '@inertiajs/react';
import { Ban, CheckCircle2, FileDown, Pencil, Send } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DetailList from '@/components/platform/DetailList';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export default function InvoiceShow({ invoice, canManage }) {
  const t = useT();
  const { locale } = useLocale();
  const f = (field) => t(`admin.invoices.fields.${field}`);
  const ratePercent = Number((Number(invoice.vat_rate) * 100).toFixed(2));

  return (
    <AdminLayout title={invoice.number}>
      <PageHeader
        title={<span dir="ltr">{invoice.number}</span>}
        actions={(
          <>
            <Button asChild variant="outline"><a href={`/admin/invoices/${invoice.id}/pdf`} target="_blank" rel="noopener"><FileDown data-icon="inline-start" />{t('admin.invoices.pdf')}</a></Button>
            {canManage && invoice.can.edit && (
              <Button asChild variant="outline"><Link href={`/admin/invoices/${invoice.id}/edit`}><Pencil data-icon="inline-start" />{t('admin.common.edit')}</Link></Button>
            )}
            {canManage && invoice.can.send && (
              <ConfirmAction href={`/admin/invoices/${invoice.id}/send`} title={t('admin.invoices.send')} description={t('admin.invoices.send_text')}>
                <Send data-icon="inline-start" />{t('admin.invoices.send')}
              </ConfirmAction>
            )}
            {canManage && invoice.can.pay && (
              <ConfirmAction href={`/admin/invoices/${invoice.id}/pay`} title={t('admin.invoices.pay')} description={t('admin.invoices.pay_text')}>
                <CheckCircle2 data-icon="inline-start" />{t('admin.invoices.pay')}
              </ConfirmAction>
            )}
            {canManage && invoice.can.void && (
              <ConfirmAction href={`/admin/invoices/${invoice.id}/void`} variant="destructive" title={t('admin.invoices.void')} description={t('admin.invoices.void_text')}>
                <Ban data-icon="inline-start" />{t('admin.invoices.void')}
              </ConfirmAction>
            )}
          </>
        )}
      >
        <div className="mt-2"><StatusBadge group="invoice_status" value={invoice.status} /></div>
      </PageHeader>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardContent className="px-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4 text-start">{f('description')}</TableHead>
                  <TableHead className="px-4 text-start">{f('quantity')}</TableHead>
                  <TableHead className="px-4 text-start">{f('unit_price')}</TableHead>
                  <TableHead className="px-4 text-start">{f('line_total')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoice.items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell className="px-4 whitespace-normal">{item.description}</TableCell>
                    <TableCell className="px-4">{formatNumber(item.quantity, locale)}</TableCell>
                    <TableCell className="px-4">{formatMoney(item.unit_price, locale)}</TableCell>
                    <TableCell className="px-4">{formatMoney(item.total, locale)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <dl className="ms-auto mt-4 grid w-full max-w-xs gap-1 px-4 text-sm">
              <div className="flex justify-between"><dt className="text-muted-foreground">{t('admin.invoices.subtotal')}</dt><dd>{formatMoney(invoice.subtotal, locale)}</dd></div>
              <div className="flex justify-between"><dt className="text-muted-foreground">{t('admin.invoices.vat', { rate: ratePercent })}</dt><dd>{formatMoney(invoice.vat, locale)}</dd></div>
              <div className="flex justify-between border-t pt-1 font-semibold"><dt>{t('admin.invoices.total')}</dt><dd>{formatMoney(invoice.total, locale)}</dd></div>
            </dl>
          </CardContent>
        </Card>
        <Card>
          <CardContent>
            <DetailList className="sm:grid-cols-1" items={[
              { label: f('client'), value: invoice.client && <Link href={`/admin/clients/${invoice.client.id}`} className="underline-offset-4 hover:underline">{invoice.client.name}</Link> },
              { label: f('project'), value: invoice.project && <Link href={`/admin/projects/${invoice.project.slug}`} className="underline-offset-4 hover:underline">{invoice.project.name}</Link> },
              { label: f('issue_date'), value: formatDate(invoice.issue_date, locale) },
              { label: f('due_date'), value: formatDate(invoice.due_date, locale) },
              invoice.sent_at && { label: t('admin.invoices.sent_at'), value: formatDate(invoice.sent_at, locale) },
              invoice.paid_at && { label: t('admin.invoices.paid_at'), value: formatDate(invoice.paid_at, locale) },
              invoice.einvoice_number && { label: t('admin.invoices.einvoice_number'), value: invoice.einvoice_number },
              { label: f('notes'), value: invoice.notes },
            ]} />
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
