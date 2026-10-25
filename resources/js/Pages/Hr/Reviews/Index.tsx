import { Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ScoreInput from '@/components/hr/ScoreInput';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface Criterion { key: string; label_ar: string; label_en: string; weight: number }

interface ReviewRow {
  id: number; employee_id: number; employee: string; employee_number: string; status: 'self' | 'manager' | 'done';
  self_scores: Record<string, number> | null; self_comment: string | null; manager_scores: Record<string, number> | null;
  manager_comment: string | null; goals: string | null; overall: string | null; can_review: boolean;
}

interface Props {
  cycles: { id: number; name: string; status: string; due_on: string; period: string }[];
  cycle: { id: number; name: string; status: string; criteria: Criterion[]; due_on: string; reviews: ReviewRow[] } | null;
  probations: { employee_id: number; employee: string; ends_on: string }[];
  companies: Option[];
  default_criteria: Criterion[];
  can_launch: boolean;
}

function LaunchDialog({ companies, defaults }: { companies: Option[]; defaults: Criterion[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const v = (key: string) => t(`hr.reviews.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ name: '', period_start: '', period_end: '', due_on: '', company_id: '', criteria: defaults });
  const errors = form.errors as Record<string, string>;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus />{v('launch')}</Button></DialogTrigger>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader><DialogTitle>{v('launch')}</DialogTitle></DialogHeader>
        <form id="launch-review" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/reviews'), { onSuccess: () => setOpen(false) }); }}>
          <FormField label={v('name')} error={errors.name} className="sm:col-span-2"><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={v('period_start')} error={errors.period_start}><Input type="date" dir="ltr" value={form.data.period_start} onChange={(event) => form.setData('period_start', event.target.value)} /></FormField>
          <FormField label={v('period_end')} error={errors.period_end}><Input type="date" dir="ltr" value={form.data.period_end} onChange={(event) => form.setData('period_end', event.target.value)} /></FormField>
          <FormField label={v('due_on')} error={errors.due_on}><Input type="date" dir="ltr" value={form.data.due_on} onChange={(event) => form.setData('due_on', event.target.value)} /></FormField>
          <FormField label={v('company')}><SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', value)} options={companies} emptyLabel={v('all_companies')} /></FormField>
          <div className="sm:col-span-2">
            <div className="mb-1 text-sm font-medium">{v('criteria_title')}</div>
            {form.data.criteria.map((criterion, index) => (
              <div key={criterion.key} className="mb-1 grid grid-cols-[1fr_1fr_5rem] gap-2">
                <Input value={criterion.label_ar} onChange={(event) => form.setData('criteria', form.data.criteria.map((item, at) => (at === index ? { ...item, label_ar: event.target.value } : item)))} />
                <Input dir="ltr" value={criterion.label_en} onChange={(event) => form.setData('criteria', form.data.criteria.map((item, at) => (at === index ? { ...item, label_en: event.target.value } : item)))} />
                <Input type="number" min="1" dir="ltr" aria-label={v('weight')} value={criterion.weight} onChange={(event) => form.setData('criteria', form.data.criteria.map((item, at) => (at === index ? { ...item, weight: Number(event.target.value) } : item)))} />
              </div>
            ))}
          </div>
        </form>
        <DialogFooter><Button type="submit" form="launch-review" disabled={form.processing}>{v('launch')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ManagerReview({ review, criteria }: { review: ReviewRow; criteria: Criterion[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const v = (key: string) => t(`hr.reviews.${key}`);
  const form = useForm({ scores: review.manager_scores ?? {} as Record<string, number>, comment: review.manager_comment ?? '', goals: review.goals ?? '' });
  const errors = form.errors as Record<string, string>;

  return (
    <Dialog>
      <DialogTrigger asChild><Button size="sm" variant={review.status === 'done' ? 'ghost' : 'default'}>{v('review')}</Button></DialogTrigger>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader><DialogTitle>{v('review')} · {review.employee}</DialogTitle></DialogHeader>
        <form id={`review-${review.id}`} className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/reviews/${review.id}`), { preserveScroll: true }); }}>
          {criteria.map((criterion) => (
            <div key={criterion.key} className="flex flex-wrap items-center justify-between gap-2 text-sm">
              <span>{locale === 'ar' ? criterion.label_ar : criterion.label_en}{review.self_scores && <span className="text-xs text-muted-foreground"> · {v('self')}: {review.self_scores[criterion.key]}</span>}</span>
              <ScoreInput value={form.data.scores[criterion.key]} onChange={(score) => form.setData('scores', { ...form.data.scores, [criterion.key]: score })} />
              {errors[`scores.${criterion.key}`] && <span className="w-full text-xs text-destructive">{errors[`scores.${criterion.key}`]}</span>}
            </div>
          ))}
          {review.self_comment && <p className="rounded bg-muted p-2 text-sm"><span className="font-medium">{v('self')}: </span>{review.self_comment}</p>}
          <FormField label={v('comment')}><Textarea rows={3} value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} /></FormField>
          <FormField label={v('goals')}><Textarea rows={3} value={form.data.goals} onChange={(event) => form.setData('goals', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form={`review-${review.id}`} disabled={form.processing}>{v('submit')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Review rounds, each employee's self and manager scores, and probations ending soon. */
export default function ReviewsIndex({ cycles, cycle, probations, companies, default_criteria: defaults, can_launch: canLaunch }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const v = (key: string) => t(`hr.reviews.${key}`);

  return (
    <SuiteLayout title={v('title')}>
      <PageHeader title={v('title')} description={v('lede')} actions={canLaunch ? <LaunchDialog companies={companies} defaults={defaults} /> : null} />
      <div className="grid gap-4 lg:grid-cols-[16rem_1fr]">
        <div className="flex flex-col gap-4">
          <Card>
            <CardContent className="flex flex-col gap-1">
              {cycles.length === 0 && <p className="text-sm text-muted-foreground">{v('none')}</p>}
              {cycles.map((item) => (
                <Link key={item.id} href={url(`/hr/reviews?cycle=${item.id}`)} className={cn('rounded-md px-2 py-2 text-sm hover:bg-muted', item.id === cycle?.id && 'bg-muted')}>
                  <div className="flex items-center justify-between gap-2"><span className="font-medium">{item.name}</span><Badge variant="outline">{v(`cycle_statuses.${item.status}`)}</Badge></div>
                  <div className="text-xs text-muted-foreground" dir="ltr">{item.period}</div>
                </Link>
              ))}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{v('probations')}</CardTitle></CardHeader>
            <CardContent className="flex flex-col gap-1 text-sm">
              {probations.length === 0 && <p className="text-muted-foreground">{v('no_probations')}</p>}
              {probations.map((row) => <Link key={row.employee_id} href={url(`/hr/employees/${row.employee_id}`)} className="flex justify-between hover:underline"><span>{row.employee}</span><span className="text-muted-foreground">{formatDate(row.ends_on, locale)}</span></Link>)}
            </CardContent>
          </Card>
        </div>
        {cycle && (
          <Card>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center justify-between gap-2">
                <span>{cycle.name} <span className="text-sm font-normal text-muted-foreground">· {v('due_on')} {formatDate(cycle.due_on, locale)}</span></span>
                {cycle.status === 'open' && cycle.reviews.some((review) => review.can_review) && <Button size="sm" variant="outline" onClick={() => router.put(url(`/hr/reviews/cycles/${cycle.id}/close`), {}, { preserveScroll: true })}>{v('close')}</Button>}
              </CardTitle>
            </CardHeader>
            <CardContent className="divide-y">
              {cycle.reviews.map((review) => (
                <div key={review.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                  <Link href={url(`/hr/employees/${review.employee_id}`)} className="font-medium hover:underline">{review.employee} <span className="text-xs font-normal text-muted-foreground" dir="ltr">{review.employee_number}</span></Link>
                  <span className="flex items-center gap-2">
                    <Badge variant={review.status === 'done' ? 'default' : 'secondary'}>{v(`statuses.${review.status}`)}</Badge>
                    {review.overall && <span className="font-semibold" dir="ltr">{review.overall} / 5</span>}
                    {review.can_review && cycle.status === 'open' && <ManagerReview review={review} criteria={cycle.criteria} />}
                  </span>
                </div>
              ))}
            </CardContent>
          </Card>
        )}
      </div>
    </SuiteLayout>
  );
}
