import { ExternalLink } from 'lucide-react';
import { useT } from '@/lib/i18n';

/** Same rights as the page's own Content-Security-Policy sandbox: scripts run, Baydaq's sign-in is out of reach. */
const SANDBOX = 'allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads';

interface Props {
  title: string;
  src: string;
}

/**
 * An uploaded standalone HTML page shown like a website inside the portal or admin: a tall framed
 * view with a link to open it on its own in a new tab.
 */
export default function DocumentViewer({ title, src }: Props) {
  const t = useT();

  return (
    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
      <div className="flex items-center gap-2 border-b bg-secondary/60 px-3 py-2">
        <span className="flex gap-1.5" aria-hidden="true">
          <span className="size-2.5 rounded-full bg-red-400/70" />
          <span className="size-2.5 rounded-full bg-amber-400/70" />
          <span className="size-2.5 rounded-full bg-emerald-400/70" />
        </span>
        <span className="min-w-0 flex-1 truncate text-center text-xs text-muted-foreground">{title}</span>
        <a href={src} target="_blank" rel="noopener" className="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-background hover:text-foreground">
          {t('portal.documents.full_screen')}
          <ExternalLink className="size-3.5" />
        </a>
      </div>
      <iframe
        src={src}
        title={t('portal.documents.frame_title', { title })}
        sandbox={SANDBOX}
        loading="lazy"
        className="block h-[75svh] min-h-[420px] w-full bg-white"
      />
    </div>
  );
}
