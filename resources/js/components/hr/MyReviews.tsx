import { useForm } from '@inertiajs/react';
import ScoreInput from '@/components/hr/ScoreInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface MyReview {
  id: number; cycle: string; due_on: string; open: boolean; status: 'self' | 'manager' | 'done'; criteria: { key: string; label: string }[];
  self_scores: Record<string, number> | null; manager_scores: Record<string, number> | null; manager_comment: string | null; goals: string | null; overall: string | null;
}

function SelfReview({ review }: { review: MyReview }) {
  const t = useT();
  const form = useForm({ scores: review.self_scores ?? {} as Record<string, number>, comment: '' });

  return (
    <form className="flex flex-col gap-2" onSubmit={(event) => { event.preventDefault(); form.post(`/me/reviews/${review.id}`, { preserveScroll: true }); }}>
      {review.criteria.map((criterion) => (
        <div key={criterion.key} className="flex flex-wrap items-center justify-between gap-2 text-sm">
          <span>{criterion.label}</span>
          <ScoreInput value={form.data.scores[criterion.key]} onChange={(score) => form.setData('scores', { ...form.data.scores, [criterion.key]: score })} />
        </div>
      ))}
      <Textarea rows={2} placeholder={t('hr.reviews.comment')} value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} />
      {Object.values(form.errors)[0] && <p className="text-xs text-destructive">{Object.values(form.errors)[0]}</p>}
      <Button size="sm" className="self-start" disabled={form.processing}>{t('hr.reviews.submit')}</Button>
    </form>
  );
}

/** My performance reviews: score myself, then see the manager's verdict and goals. */
export default function MyReviews({ reviews }: { reviews: MyReview[] }) {
  const t = useT();
  const { locale } = useLocale();
  const v = (key: string) => t(`hr.reviews.${key}`);

  if (reviews.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{v('mine')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-4">
        {reviews.map((review) => (
          <div key={review.id} className="rounded-lg border p-3">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
              <span className="font-medium">{review.cycle}</span>
              <span className="flex items-center gap-2"><Badge variant="secondary">{v(`statuses.${review.status}`)}</Badge><span className="text-muted-foreground">{v('due_on')} {formatDate(review.due_on, locale)}</span></span>
            </div>
            {review.status === 'self' && review.open && <SelfReview review={review} />}
            {review.status === 'done' && (
              <div className="flex flex-col gap-1 text-sm">
                <div className="text-lg font-semibold" dir="ltr">{review.overall} / 5</div>
                {review.manager_comment && <p><span className="font-medium">{v('comment')}: </span>{review.manager_comment}</p>}
                {review.goals && <p><span className="font-medium">{v('goals')}: </span>{review.goals}</p>}
              </div>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
