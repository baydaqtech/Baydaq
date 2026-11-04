import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { AnnouncementItem } from '@/Pages/Hr/Announcements/Index';
import { Wall, type RecognitionWall } from '@/Pages/Hr/Recognition/Index';
import type { SurveyItem } from '@/Pages/Hr/Surveys/Index';
import { TONES, type CalendarEvent } from '@/Pages/Suite/Calendar';
import type { Option } from '@/types/resources';

export type MyAnnouncement = AnnouncementItem & { read: boolean; acknowledged_at: string | null };
export type MyRecognition = RecognitionWall & { colleagues: Option[]; badges: string[] };

/** Policies, circulars and news for me; acknowledge the ones that ask for it. */
export function MyAnnouncements({ items }: { items: MyAnnouncement[] }) {
  const t = useT();
  const { locale } = useLocale();
  const a = (key: string, replace?: Record<string, string>) => t(`hr.announcements.${key}`, replace);
  const [open, setOpen] = useState<number | null>(null);
  const read = (item: MyAnnouncement, acknowledge = false) => router.post(`/me/announcements/${item.id}/read`, { acknowledge }, { preserveScroll: true });

  if (items.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{a('mine')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">
        {items.map((item) => (
          <div key={item.id} className="py-2">
            <button type="button" className="flex w-full flex-wrap items-center gap-2 text-start" onClick={() => { setOpen(open === item.id ? null : item.id); if (!item.read) read(item); }}>
              <Badge variant="outline">{a(`kinds.${item.kind}`)}</Badge>
              <span className={cn(!item.read && 'font-semibold')}>{item.title}</span>
              <span className="text-xs text-muted-foreground">{formatDate(item.published_at, locale)}</span>
              {item.requires_ack && !item.acknowledged_at && <Badge variant="destructive">{a('acknowledge')}</Badge>}
            </button>
            {open === item.id && (
              <div className="mt-2 flex flex-col gap-2">
                <p className="whitespace-pre-line">{item.body}</p>
                {item.file_name && <a className="inline-flex items-center gap-1 text-primary hover:underline" href={`/me/announcements/${item.id}/file`}><Paperclip className="size-4" />{item.file_name}</a>}
                {item.requires_ack && (item.acknowledged_at
                  ? <p className="text-xs text-muted-foreground">{a('acknowledged', { date: formatDate(item.acknowledged_at, locale) })}</p>
                  : <Button size="sm" className="self-start" onClick={() => read(item, true)}>{a('acknowledge')}</Button>)}
              </div>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function SurveyForm({ survey }: { survey: SurveyItem }) {
  const t = useT();
  const s = (key: string) => t(`hr.surveys.${key}`);
  const [answers, setAnswers] = useState<Record<string, string | number>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const scale = (from: number, to: number) => Array.from({ length: to - from + 1 }, (_, index) => from + index);

  return (
    <div className="flex flex-col gap-3 py-3">
      <p className="font-medium">{survey.title}{survey.anonymous && <Badge variant="outline" className="ms-2">{s('anonymous')}</Badge>}</p>
      {survey.description && <p className="text-muted-foreground">{survey.description}</p>}
      {survey.questions.map((question) => (
        <div key={question.id} className="flex flex-col gap-1">
          <p>{question.label}</p>
          {(question.type === 'rating' || question.type === 'nps') && (
            <div className="flex flex-wrap gap-1">
              {scale(question.type === 'nps' ? 0 : 1, question.type === 'nps' ? 10 : 5).map((value) => (
                <button key={value} type="button" onClick={() => setAnswers({ ...answers, [question.id]: value })} className={cn('size-8 rounded-md border text-xs', answers[question.id] === value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted')}>{value}</button>
              ))}
            </div>
          )}
          {question.type === 'choice' && <SelectInput value={String(answers[question.id] ?? '')} onChange={(value) => setAnswers({ ...answers, [question.id]: value })} options={question.options.map((option) => ({ value: option, label: option }))} />}
          {question.type === 'text' && <Textarea rows={2} value={String(answers[question.id] ?? '')} onChange={(event) => setAnswers({ ...answers, [question.id]: event.target.value })} />}
          {errors[`answers.${question.id}`] && <p className="text-xs text-destructive">{errors[`answers.${question.id}`]}</p>}
        </div>
      ))}
      {errors.answers && <p className="text-xs text-destructive">{errors.answers}</p>}
      <Button size="sm" className="self-start" onClick={() => router.post(`/me/surveys/${survey.id}`, { answers }, { preserveScroll: true, onError: setErrors })}>{s('submit')}</Button>
    </div>
  );
}

/** Surveys waiting for my answers. */
export function MySurveys({ surveys }: { surveys: SurveyItem[] }) {
  const t = useT();

  if (surveys.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.surveys.mine')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">{surveys.map((survey) => <SurveyForm key={survey.id} survey={survey} />)}</CardContent>
    </Card>
  );
}

/** Thank a colleague, and the company's recognition wall. */
export function MyRecognitionCard({ data }: { data: MyRecognition }) {
  const t = useT();
  const r = (key: string) => t(`hr.recognition.${key}`);
  const form = useForm({ employee_id: data.colleagues[0]?.value ?? '', badge: 'thanks', message: '' });

  return (
    <section className="flex flex-col gap-3">
      <Card>
        <CardHeader><CardTitle className="text-base">{r('mine')}</CardTitle></CardHeader>
        <CardContent>
          {data.colleagues.length > 0 && (
            <form className="grid gap-3 sm:grid-cols-4" onSubmit={(event) => { event.preventDefault(); form.post('/me/recognition', { preserveScroll: true, onSuccess: () => form.reset('message') }); }}>
              <FormField label={r('employee')} error={form.errors.employee_id}><SelectInput value={String(form.data.employee_id)} onChange={(value) => form.setData('employee_id', Number(value))} options={data.colleagues} /></FormField>
              <FormField label={r('badge')} error={form.errors.badge}><SelectInput value={form.data.badge} onChange={(value) => form.setData('badge', value)} options={data.badges.map((value) => ({ value, label: t(`hr.recognition.badges.${value}`) }))} /></FormField>
              <FormField label={r('message')} error={form.errors.message} className="sm:col-span-2"><Input value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} /></FormField>
              <div className="sm:col-span-4"><Button type="submit" size="sm" disabled={form.processing || form.data.message === ''}>{r('give')}</Button></div>
            </form>
          )}
        </CardContent>
      </Card>
      <Wall wall={data} />
    </section>
  );
}

export interface MyMessage { id: number; subject: string; body: string; sent_at: string; read: boolean }

/** My inbox from the message builder. */
export function MyMessages({ messages }: { messages: MyMessage[] }) {
  const t = useT();
  const { locale } = useLocale();
  const [open, setOpen] = useState<number | null>(null);

  if (messages.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.messages.inbox')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">
        {messages.map((message) => (
          <div key={message.id} className="py-2">
            <button type="button" className="flex w-full items-center justify-between gap-2 text-start" onClick={() => { setOpen(open === message.id ? null : message.id); if (!message.read) router.post(`/me/messages/${message.id}/read`, {}, { preserveScroll: true }); }}>
              <span className={cn(!message.read && 'font-semibold')}>{message.subject}</span>
              <span className="text-xs text-muted-foreground">{formatDate(message.sent_at, locale)}</span>
            </button>
            {open === message.id && <p className="mt-2 whitespace-pre-line">{message.body}</p>}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

/** My next 30 days: holidays, my leave, trainings, surveys closing. */
export function MyUpcoming({ events }: { events: CalendarEvent[] }) {
  const t = useT();
  const { locale } = useLocale();

  if (events.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('suite.calendar.upcoming')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-1 text-sm">
        {events.map((event) => (
          <div key={event.key} className="flex items-center gap-2">
            <span className="w-24 shrink-0 text-xs text-muted-foreground">{formatDate(event.date, locale)}</span>
            <span className={cn('rounded px-1.5 py-0.5 text-xs', TONES[event.tone])}>{t(`suite.calendar.categories.${event.category}`)}</span>
            <span className="truncate">{event.title}</span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
