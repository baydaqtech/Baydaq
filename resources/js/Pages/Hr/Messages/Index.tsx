import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Send } from 'lucide-react';
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

interface Template {
  id: number; name: string; trigger: string; event: string | null; weekday: number | null; month_day: number | null; days_before: number | null;
  channels: string[]; subject: string; body: string; is_active: boolean; audience: string;
  audience_label: string; sent: number; last_sent: string | null; can_update: boolean;
}

interface Props { templates: Template[]; audiences: Option[]; triggers: string[]; events: string[]; channels: string[]; placeholders: string[] }

const SAMPLE: Record<string, string> = { name: 'Sara Alqahtani', first_name: 'Sara', job: 'Barista', branch: 'Olaya', company: 'Al Noor Cafes', hired_on: '2024-03-01', years: '2', date: '2026-11-01', contract_ends_on: '2027-02-28' };

function fill(text: string): string {
  return text.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (match, key: string) => SAMPLE[key] ?? match);
}

function TemplateForm({ template, props, onDone }: { template?: Template; props: Props; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const m = (key: string) => t(`hr.messages.${key}`);
  const form = useForm({
    name: template?.name ?? '', trigger: template?.trigger ?? 'manual', event: template?.event ?? 'hired', weekday: String(template?.weekday ?? 0),
    month_day: String(template?.month_day ?? 1), days_before: String(template?.days_before ?? 30), channels: template?.channels ?? ['inapp'],
    subject: template?.subject ?? '', body: template?.body ?? '', is_active: template?.is_active ?? true, audience: template?.audience ?? String(props.audiences[0]?.value ?? ''),
  });
  const errors = form.errors as Record<string, string>;
  const submit = () => (template ? form.put(url(`/hr/messages/${template.id}`), { preserveScroll: true, onSuccess: onDone }) : form.post(url('/hr/messages'), { preserveScroll: true, onSuccess: onDone }));
  const toggleChannel = (channel: string) => form.setData('channels', form.data.channels.includes(channel) ? form.data.channels.filter((item) => item !== channel) : [...form.data.channels, channel]);

  return (
    <form className="grid gap-3 lg:grid-cols-2" onSubmit={(event) => { event.preventDefault(); submit(); }}>
      <div className="grid gap-3 sm:grid-cols-2">
        <FormField label={m('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
        <FormField label={t('hr.engagement.audience')} error={form.errors.audience}><SelectInput value={form.data.audience} onChange={(value) => form.setData('audience', value)} options={props.audiences} /></FormField>
        <FormField label={m('trigger')} error={form.errors.trigger}><SelectInput value={form.data.trigger} onChange={(value) => form.setData('trigger', value)} options={props.triggers.map((value) => ({ value, label: t(`hr.messages.triggers.${value}`) }))} /></FormField>
        {form.data.trigger === 'event' && <FormField label={m('event')} error={form.errors.event}><SelectInput value={form.data.event} onChange={(value) => form.setData('event', value)} options={props.events.map((value) => ({ value, label: t(`hr.messages.events.${value}`) }))} /></FormField>}
        {form.data.trigger === 'weekly' && <FormField label={m('weekday')} error={form.errors.weekday}><SelectInput value={form.data.weekday} onChange={(value) => form.setData('weekday', value)} options={[0, 1, 2, 3, 4, 5, 6].map((day) => ({ value: String(day), label: t(`hr.remote.days.${day}`) }))} /></FormField>}
        {form.data.trigger === 'monthly' && <FormField label={m('month_day')} error={form.errors.month_day}><Input type="number" min="1" max="28" dir="ltr" value={form.data.month_day} onChange={(event) => form.setData('month_day', event.target.value)} /></FormField>}
        {form.data.trigger === 'event' && form.data.event === 'contract_ending' && <FormField label={m('days_before')} error={form.errors.days_before}><Input type="number" min="1" max="180" dir="ltr" value={form.data.days_before} onChange={(event) => form.setData('days_before', event.target.value)} /></FormField>}
        <FormField label={m('channels')} error={errors.channels} className="sm:col-span-2">
          <div className="flex flex-wrap gap-4">
            {props.channels.map((channel) => (
              <label key={channel} className="flex items-center gap-2 text-sm"><Checkbox checked={channel === 'inapp' || form.data.channels.includes(channel)} disabled={channel === 'inapp'} onCheckedChange={() => toggleChannel(channel)} />{t(`hr.messages.channel_names.${channel}`)}</label>
            ))}
          </div>
        </FormField>
        <FormField label={m('subject')} error={form.errors.subject} className="sm:col-span-2"><Input value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} /></FormField>
        <FormField label={m('body')} error={form.errors.body} className="sm:col-span-2"><Textarea rows={6} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} /></FormField>
        <div className="flex flex-wrap gap-1 sm:col-span-2">
          <span className="text-xs text-muted-foreground">{m('placeholders')}:</span>
          {props.placeholders.map((key) => <button key={key} type="button" className="rounded border px-1.5 text-xs hover:bg-muted" dir="ltr" onClick={() => form.setData('body', `${form.data.body}{{${key}}}`)}>{`{{${key}}}`}</button>)}
        </div>
        <label className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.is_active} onCheckedChange={(state) => form.setData('is_active', state === true)} />{m('active')}</label>
        <div className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>
      </div>
      <div className="rounded-md border bg-muted/40 p-3 text-sm">
        <p className="mb-2 text-xs font-medium text-muted-foreground">{m('preview')}</p>
        <p className="font-semibold">{fill(form.data.subject)}</p>
        <p className="mt-2 whitespace-pre-line">{fill(form.data.body)}</p>
      </div>
    </form>
  );
}

/** Message templates: by hand, weekly, monthly or on employee events. */
export default function MessagesIndex(props: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const m = (key: string, replace?: Record<string, string>) => t(`hr.messages.${key}`, replace);
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const when = (template: Template) => template.trigger === 'event' ? m(`events.${template.event}`)
    : template.trigger === 'weekly' ? `${m('triggers.weekly')} · ${t(`hr.remote.days.${template.weekday}`)}`
      : template.trigger === 'monthly' ? `${m('triggers.monthly')} · ${template.month_day}` : m('triggers.manual');

  return (
    <SuiteLayout title={m('title')}>
      <PageHeader title={m('title')} description={m('lede')} actions={props.audiences.length > 0 && <Button onClick={() => setEditing(editing === 'new' ? null : 'new')}><Plus data-icon="inline-start" />{m('new')}</Button>} />
      {editing === 'new' && <Card className="mb-4"><CardContent className="py-4"><TemplateForm props={props} onDone={() => setEditing(null)} /></CardContent></Card>}
      {props.templates.length === 0 && <p className="text-sm text-muted-foreground">{m('none')}</p>}
      <div className="flex flex-col gap-4">
        {props.templates.map((template) => (
          <Card key={template.id}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                {template.name}
                <Badge variant="outline">{when(template)}</Badge>
                <Badge variant={template.is_active ? 'default' : 'outline'}>{template.is_active ? m('active') : m('paused')}</Badge>
                <span className="text-sm font-normal text-muted-foreground">· {template.audience_label} · {template.channels.map((channel) => t(`hr.messages.channel_names.${channel}`)).join('، ')} · {m('sent_count', { count: String(template.sent) })}</span>
                {template.can_update && (
                  <span className="ms-auto flex gap-1">
                    <Button size="sm" variant="outline" onClick={() => router.post(url(`/hr/messages/${template.id}/send`), {}, { preserveScroll: true })}><Send data-icon="inline-start" />{m('send_now')}</Button>
                    <Button size="sm" variant="ghost" onClick={() => setEditing(editing === template.id ? null : template.id)}>{t('common.actions.edit')}</Button>
                  </span>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-sm">
              {editing === template.id ? <TemplateForm template={template} props={props} onDone={() => setEditing(null)} /> : <><p className="font-medium">{template.subject}</p><p className="line-clamp-3 whitespace-pre-line text-muted-foreground">{template.body}</p></>}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
