import { FileDown } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

/** The client's invoices with a PDF download per row. */
export default function InvoiceTable({ invoices, showProject = false }) {
  const t = useT();
  const { locale } = useLocale();
  const b = (field) => t(`portal.billing.${field}`);

  return (
    <DataTable
      rows={invoices}
      empty={b('none')}
      columns={[
        { key: 'number', label: b('number'), render: (invoice) => <span className="font-mono text-xs" dir="ltr">{invoice.number}</span> },
        showProject && { key: 'project', label: b('project'), render: (invoice) => invoice.project ?? '—' },
        { key: 'issue_date', label: b('issue_date'), render: (invoice) => formatDate(invoice.issue_date, locale) },
        { key: 'due_date', label: b('due_date'), render: (invoice) => formatDate(invoice.due_date, locale) },
        { key: 'total', label: b('total'), render: (invoice) => <span className="font-medium">{formatMoney(invoice.total, locale, invoice.currency)}</span> },
        { key: 'status', label: b('status'), render: (invoice) => <StatusBadge group="invoice_status" value={invoice.status} /> },
        { key: 'pdf', label: '', className: 'text-end', render: (invoice) => (
          <Button asChild size="xs" variant="outline">
            <a href={invoice.pdf_url} target="_blank" rel="noopener"><FileDown data-icon="inline-start" />{b('pdf')}</a>
          </Button>
        ) },
      ].filter(Boolean)}
    />
  );
}
