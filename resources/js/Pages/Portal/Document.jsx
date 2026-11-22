import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import DocumentComments from '@/components/platform/DocumentComments';
import DocumentViewer from '@/components/platform/DocumentViewer';
import { Badge } from '@/components/ui/badge';
import PortalLayout from '@/layouts/PortalLayout';
import { formatDateTime, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

/** A document the Baydaq team shared: the page itself, shown like a website, and the comments under it. */
export default function Document({ document: doc, comments }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <PortalLayout title={doc.title}>
      <Link href="/app" className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4 rtl:-scale-x-100" />{t('portal.documents.back')}
      </Link>

      <div className="mb-4">
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <Badge variant="outline">{t('portal.documents.number', { number: formatNumber(doc.number, locale) })}</Badge>
          <time dateTime={doc.uploaded_at}>{formatDateTime(doc.uploaded_at, locale)}</time>
          {doc.uploaded_by && <span>{t('portal.documents.by', { name: doc.uploaded_by })}</span>}
        </div>
        <h1 className="mt-2 text-2xl font-semibold">{doc.title}</h1>
        {doc.note && <p className="mt-1 max-w-3xl text-muted-foreground">{doc.note}</p>}
      </div>

      <DocumentViewer title={doc.title} src={`/app/documents/${doc.id}/view`} />

      <div className="mt-8 max-w-3xl">
        <DocumentComments comments={comments} action={`/app/documents/${doc.id}/comments`} />
      </div>
    </PortalLayout>
  );
}
