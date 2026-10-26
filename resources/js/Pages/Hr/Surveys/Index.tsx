import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface SurveyQuestion { id: string; type: string; label: string; options: string[] }
export interface SurveyItem { id: number; title: string; description: string | null; questions: SurveyQuestion[]; anonymous: boolean; status: string; closes_on: string | null }

interface QuestionResult extends SurveyQuestion { answered: number; average: number | null; enps: number | null; counts: Record<string, number> | null; texts: string[] | null }

interface Props {
  surveys: (SurveyItem & { audience: string; can_close: boolean; results: { count: number; hidden: boolean; questions: QuestionResult[] } })[];
  audiences: Option[];
  types: string[];
  min_anonymous: number;
}

interface DraftQuestion { type: string; label: string; options: string }

function SurveyForm({ audiences, types, onDone }: { audiences: Option[]; types: string[]; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const s = (key: string) => t(`hr.surveys.${key}`);
  const [questions, setQuestions] = useState<DraftQuestion[]>([{ type: 'nps', label: '', options: '' }]);
  const form = useForm({ title: '', description: '', anonymous: true, closes_on: '', audience: String(audiences[0]?.value ?? '') });
  const errors = form.errors as Record<string, string>;
  const update = (index: number, patch: Partial<DraftQuestion>) => setQuestions(questions.map((question, position) => (position === index ? { ...question, ...patch } : question)));
  const submit = () => router.post(url('/hr/surveys'), {
    ...form.data,
    questions: questions.map((question) => ({ type: question.type, label: question.label, options: question.type === 'choice' ? question.options.split('\n') : [] })),
  }, { preserveScroll: true, onSuccess: onDone, onError: (bag) => form.setError(bag) });

  return (
    <Card className="mb-4"><CardContent className="flex flex-col gap-3 py-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <FormField label={s('title_field')} error={form.errors.title}><Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></FormField>
        <FormField label={t('hr.engagement.audience')} error={form.errors.audience}><SelectInput value={form.data.audience} onChange={(value) => form.setData('audience', value)} options={audiences} /></FormField>
        <FormField label={s('closes_on')} error={form.errors.closes_on}><Input type="date" dir="ltr" value={form.data.closes_on} onChange={(event) => form.setData('closes_on', event.target.value)} /></FormField>
        <FormField label={s('description')} error={form.errors.description} className="sm:col-span-3"><Textarea rows={2} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
        <label className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.anonymous} onCheckedChange={(state) => form.setData('anonymous', state === true)} />{s('anonymous')}</label>
      </div>
      <h3 className="text-sm font-semibold">{s('questions')}</h3>
      {questions.map((question, index) => (
        <div key={index} className="grid gap-2 rounded-md border p-3 sm:grid-cols-[10rem_1fr_auto]">
          <SelectInput value={question.type} onChange={(type) => update(index, { type })} options={types.map((value) => ({ value, label: t(`hr.surveys.types.${value}`) }))} />
          <div className="flex flex-col gap-2">
            <Input placeholder={s('question')} value={question.label} onChange={(event) => update(index, { label: event.target.value })} />
            {question.type === 'choice' && <Textarea rows={3} placeholder={s('options')} value={question.options} onChange={(event) => update(index, { options: event.target.value })} />}
            {errors[`questions.${index}.label`] && <p className="text-xs text-destructive">{errors[`questions.${index}.label`]}</p>}
          </div>
          <Button type="button" size="icon" variant="ghost" disabled={questions.length === 1} onClick={() => setQuestions(questions.filter((_, position) => position !== index))}><Trash2 /></Button>
        </div>
      ))}
      <div className="flex gap-2">
        <Button type="button" variant="outline" onClick={() => setQuestions([...questions, { type: 'rating', label: '', options: '' }])}><Plus data-icon="inline-start" />{s('add_question')}</Button>
        <Button type="button" onClick={submit}>{t('common.actions.save')}</Button>
      </div>
    </CardContent></Card>
  );
}

function Result({ question }: { question: QuestionResult }) {
  const t = useT();
  const s = (key: string, replace?: Record<string, string>) => t(`hr.surveys.${key}`, replace);
  const total = Math.max(1, question.answered);

  return (
    <div className="flex flex-col gap-1 py-2">
      <p className="font-medium">{question.label} <span className="font-normal text-muted-foreground">· {s('responses', { count: String(question.answered) })}</span></p>
      <p className="flex gap-3 text-muted-foreground">
        {question.average !== null && <span>{s('average', { value: String(question.average) })}</span>}
        {question.enps !== null && <span className="font-medium text-foreground">{s('enps', { value: String(question.enps) })}</span>}
      </p>
      {question.counts && Object.entries(question.counts).map(([answer, count]) => (
        <div key={answer} className="flex items-center gap-2 text-xs">
          <span className="w-24 truncate">{answer}</span>
          <div className="h-2 flex-1 rounded bg-muted"><div className="h-2 rounded bg-primary" style={{ width: `${(count / total) * 100}%` }} /></div>
          <span className="w-8 text-end">{count}</span>
        </div>
      ))}
      {question.texts && question.texts.length > 0 && <ul className="list-disc ps-5 text-muted-foreground">{question.texts.map((text, index) => <li key={index}>{text}</li>)}</ul>}
    </div>
  );
}

/** Staff surveys and their results. */
export default function SurveysIndex({ surveys, audiences, types, min_anonymous: minAnonymous }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const s = (key: string, replace?: Record<string, string>) => t(`hr.surveys.${key}`, replace);
  const [adding, setAdding] = useState(false);

  return (
    <SuiteLayout title={s('title')}>
      <PageHeader title={s('title')} description={s('lede', { min: String(minAnonymous) })} actions={audiences.length > 0 && <Button onClick={() => setAdding(!adding)}><Plus data-icon="inline-start" />{s('new')}</Button>} />
      {adding && <SurveyForm audiences={audiences} types={types} onDone={() => setAdding(false)} />}
      {surveys.length === 0 && <p className="text-sm text-muted-foreground">{s('none')}</p>}
      <div className="flex flex-col gap-4">
        {surveys.map((survey) => (
          <Card key={survey.id}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                {survey.title}
                <Badge variant="outline">{survey.anonymous ? s('anonymous') : s('named')}</Badge>
                <Badge variant={survey.status === 'open' ? 'default' : 'outline'}>{s(`statuses.${survey.status}`)}</Badge>
                <span className="text-sm font-normal text-muted-foreground">· {survey.audience} · {s('responses', { count: String(survey.results.count) })}</span>
                {survey.can_close && <Button size="sm" variant="ghost" className="ms-auto" onClick={() => router.put(url(`/hr/surveys/${survey.id}/close`), {}, { preserveScroll: true })}>{s('close')}</Button>}
              </CardTitle>
            </CardHeader>
            <CardContent className="divide-y text-sm">
              {survey.results.hidden ? <p className="text-muted-foreground">{s('hidden', { min: String(minAnonymous) })}</p> : survey.results.questions.map((question) => <Result key={question.id} question={question} />)}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
