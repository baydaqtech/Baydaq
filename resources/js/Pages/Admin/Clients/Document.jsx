import { Link } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DocumentComments from '@/components/platform/DocumentComments';
import DocumentViewer from '@/components/platform/DocumentViewer';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { formatDateTime, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

/** A document shared with a client, as the client sees it, with the comments and the team's replies. */
export default function ClientDocument({ client, document: doc, comments }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();
  const base = `/admin/clients/${client.id}/documents/${doc.id}`;

  return (
    <AdminLayout title={doc.title}>
      <Link href={`/admin/clients/${client.id}`} className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4 rtl:-scale-x-100" />{t('admin.clients.documents.back')} · {client.name}
      </Link>

      <PageHeader
        title={doc.title}
        description={doc.note}
        actions={can('clients.manage') && (
          <ConfirmAction
            href={base}
            method="delete"
            size="sm"
            variant="outline"
            title={t('admin.clients.documents.delete_title')}
            description={t('admin.clients.documents.delete_text')}
            confirmLabel={t('admin.clients.documents.delete')}
          >
            <Trash2 data-icon="inline-start" />{t('admin.clients.documents.delete')}
          </ConfirmAction>
        )}
      >
        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <Badge variant="outline">{t('portal.documents.number', { number: formatNumber(doc.number, locale) })}</Badge>
          <time dateTime={doc.uploaded_at}>{formatDateTime(doc.uploaded_at, locale)}</time>
          {doc.uploaded_by && <span>{t('portal.documents.by', { name: doc.uploaded_by })}</span>}
          <span dir="ltr">{doc.file_name}</span>
        </div>
      </PageHeader>

      <DocumentViewer title={doc.title} src={`${base}/view`} />

      <div className="mt-8 max-w-3xl">
        <DocumentComments comments={comments} action={can('clients.manage') ? `${base}/comments` : undefined} />
      </div>
    </AdminLayout>
  );
}
