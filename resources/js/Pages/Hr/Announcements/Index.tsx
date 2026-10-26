import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Paperclip, Plus } from 'lucide-react';
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
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface AnnouncementItem { id: number; kind: string; title: string; body: string; requires_ack: boolean; published_at: string; file_name: string | null }

interface Props {
  announcements: (AnnouncementItem & { audience: string; people: number; read: number; acknowledged: number; waiting: string[] })[];
  audiences: Option[];
  kinds: string[];
}

function PublishForm({ audiences, kinds, onDone }: { audiences: Option[]; kinds: string[]; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const a = (key: string) => t(`hr.announcements.${key}`);
  const form = useForm<{ kind: string; title: string; body: string; requires_ack: boolean; audience: string; file: File | null }>({ kind: 'circular', title: '', body: '', requires_ack: false, audience: String(audiences[0]?.value ?? ''), file: null });

  return (
    <Card className="mb-4"><CardContent className="py-4">
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/announcements'), { forceFormData: true, preserveScroll: true, onSuccess: onDone }); }}>
        <FormField label={a('kind')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds.map((value) => ({ value, label: t(`hr.announcements.kinds.${value}`) }))} /></FormField>
        <FormField label={t('hr.engagement.audience')} error={form.errors.audience}><SelectInput value={form.data.audience} onChange={(value) => form.setData('audience', value)} options={audiences} /></FormField>
        <FormField label={a('title_field')} error={form.errors.title}><Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></FormField>
        <FormField label={a('body')} error={form.errors.body} className="sm:col-span-3"><Textarea rows={6} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} /></FormField>
        <FormField label={a('file')} error={form.errors.file}><Input type="file" accept=".pdf,.doc,.docx,image/*" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></FormField>
        <label className="flex items-center gap-2 text-sm sm:col-span-2"><Checkbox checked={form.data.requires_ack} onCheckedChange={(state) => form.setData('requires_ack', state === true)} />{a('requires_ack')}</label>
        <div className="sm:col-span-3"><Button type="submit" disabled={form.processing}>{a('new')}</Button></div>
      </form>
    </CardContent></Card>
  );
}

/** Policies, circulars and news, with read and acknowledgement progress. */
export default function AnnouncementsIndex({ announcements, audiences, kinds }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const a = (key: string, replace?: Record<string, string>) => t(`hr.announcements.${key}`, replace);
  const [adding, setAdding] = useState(false);

  return (
    <SuiteLayout title={a('title')}>
      <PageHeader title={a('title')} description={a('lede')} actions={audiences.length > 0 && <Button onClick={() => setAdding(!adding)}><Plus data-icon="inline-start" />{a('new')}</Button>} />
      {adding && <PublishForm audiences={audiences} kinds={kinds} onDone={() => setAdding(false)} />}
      {announcements.length === 0 && <p className="text-sm text-muted-foreground">{a('none')}</p>}
      <div className="flex flex-col gap-4">
        {announcements.map((item) => (
          <Card key={item.id}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                <Badge variant="outline">{a(`kinds.${item.kind}`)}</Badge>{item.title}
                <span className="text-sm font-normal text-muted-foreground">· {item.audience} · {formatDate(item.published_at, locale)}</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 text-sm">
              <p className="whitespace-pre-line">{item.body}</p>
              {item.file_name && <a className="inline-flex items-center gap-1 text-primary hover:underline" href={url(`/hr/announcements/${item.id}/file`)}><Paperclip className="size-4" />{item.file_name}</a>}
              <p className="text-muted-foreground">{a('progress', { read: String(item.read), people: String(item.people), ack: String(item.acknowledged) })}</p>
              {item.waiting.length > 0 && <p className="text-xs text-muted-foreground"><span className="font-medium">{a('waiting')}:</span> {item.waiting.join('، ')}</p>}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
