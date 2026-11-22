import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface DocumentComment {
  id: number;
  body: string;
  by_team: boolean;
  author: string | null;
  at: string;
}

interface Props {
  comments: DocumentComment[];
  /** Where a new comment is posted; without it the list is read-only. */
  action?: string;
}

/** Comments on a shared document, oldest first, with the Baydaq team's replies marked. */
export default function DocumentComments({ comments, action }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const form = useForm({ body: '' });

  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(action ?? '', { preserveScroll: true, onSuccess: () => form.reset() });
  }

  return (
    <section aria-labelledby="comments-title" className="flex flex-col gap-4">
      <h2 id="comments-title" className="text-lg font-semibold">{t('portal.documents.comments')}</h2>

      {comments.length === 0
        ? <p className="text-sm text-muted-foreground">{t('portal.documents.no_comments')}</p>
        : (
          <ol className="flex flex-col gap-3">
            {comments.map((comment) => (
              <li key={comment.id} className={comment.by_team ? 'rounded-lg border border-brass/30 bg-brass/5 p-3' : 'rounded-lg border bg-card p-3'}>
                <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                  <span className="font-medium text-foreground">{comment.author ?? '—'}</span>
                  {comment.by_team && <Badge className="bg-walnut text-ivory">{t('portal.documents.team')}</Badge>}
                  <time dateTime={comment.at}>{formatDateTime(comment.at, locale)}</time>
                </div>
                <p className="whitespace-pre-line text-sm">{comment.body}</p>
              </li>
            ))}
          </ol>
        )}

      {action && (
        <form onSubmit={submit} className="flex flex-col gap-2">
          <label htmlFor="comment-body" className="text-sm font-medium">{t('portal.documents.write')}</label>
          <Textarea
            id="comment-body"
            rows={3}
            maxLength={2000}
            placeholder={t('portal.documents.placeholder')}
            value={form.data.body}
            onChange={(event) => form.setData('body', event.target.value)}
            aria-invalid={!!form.errors.body}
          />
          {form.errors.body && <p className="text-sm text-destructive">{form.errors.body}</p>}
          <div>
            <Button type="submit" disabled={form.processing || form.data.body.trim() === ''}>
              <Send data-icon="inline-start" />{t('portal.documents.send')}
            </Button>
          </div>
        </form>
      )}
    </section>
  );
}
