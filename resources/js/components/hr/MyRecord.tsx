import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface MySeparation {
  id: number; kind: string; status: string; submitted_on: string; last_day: string; deemed_accept_on: string | null; deemed: boolean; can_withdraw: boolean;
}

export interface MyViolation {
  id: number; category: string; occurred_on: string; occurrence: number; description: string | null; penalty: string; status: string; employee_response: string | null;
}

export interface RecordData { separation: MySeparation | null; violations: MyViolation[]; notice_days: number }

function Answer({ violation }: { violation: MyViolation }) {
  const t = useT();
  const form = useForm({ response: '' });

  return (
    <form className="mt-2 flex flex-col gap-2" onSubmit={(event) => { event.preventDefault(); form.post(`/me/violations/${violation.id}/response`, { preserveScroll: true }); }}>
      <Textarea rows={2} value={form.data.response} onChange={(event) => form.setData('response', event.target.value)} />
      {form.errors.response && <p className="text-xs text-destructive">{form.errors.response}</p>}
      <Button size="sm" className="self-start" disabled={form.processing || form.data.response.trim() === ''}>{t('hr.violations.respond')}</Button>
    </form>
  );
}

/** My leaving and my disciplinary record: resign (and withdraw in time), and answer a violation. */
export default function MyRecord({ data, canResign }: { data: RecordData; canResign: boolean }) {
  const t = useT();
  const { locale } = useLocale();
  const s = (key: string, replace?: Record<string, string>) => t(`hr.separations.${key}`, replace);
  const v = (key: string) => t(`hr.violations.${key}`);
  const [resigning, setResigning] = useState(false);
  const form = useForm({ last_day: '', reason: '' });
  const separation = data.separation;
  const open = separation && ['submitted', 'deferred', 'accepted'].includes(separation.status);

  if (!separation && data.violations.length === 0 && !canResign) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{s('my_record')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-4">
        {data.violations.map((violation) => (
          <div key={violation.id} className="rounded-lg border p-3 text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <span className="font-medium">{v(`categories.${violation.category}`)}</span>
              <Badge variant="outline">{formatDate(violation.occurred_on, locale)}</Badge>
              <Badge variant={violation.status === 'closed' ? 'default' : 'secondary'}>{v(`statuses.${violation.status}`)}</Badge>
              {violation.status === 'closed' && <span className="text-muted-foreground">{v(`penalties.${violation.penalty}`)}</span>}
            </div>
            {violation.description && <p className="mt-1 text-muted-foreground">{violation.description}</p>}
            {violation.employee_response
              ? <p className="mt-2 rounded bg-muted p-2"><span className="font-medium">{v('response')}: </span>{violation.employee_response}</p>
              : violation.status === 'notified' && <Answer violation={violation} />}
          </div>
        ))}
        {separation && (
          <div className="rounded-lg border p-3 text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <span className="font-medium">{s(`kinds.${separation.kind}`)}</span>
              <Badge>{s(`statuses.${separation.status}`)}</Badge>
            </div>
            <p className="mt-1 text-muted-foreground">{s('last_day')}: {formatDate(separation.last_day, locale)}{separation.deemed_accept_on && open && separation.status !== 'accepted' ? ` · ${s('deemed_accept_on')}: ${formatDate(separation.deemed_accept_on, locale)}` : ''}</p>
            {separation.can_withdraw && <Button size="sm" variant="outline" className="mt-2" onClick={() => router.post(`/me/resignation/${separation.id}/withdraw`, {}, { preserveScroll: true })}>{s('withdraw')}</Button>}
          </div>
        )}
        {canResign && !open && (resigning ? (
          <form className="flex flex-col gap-3 rounded-lg border p-3" onSubmit={(event) => { event.preventDefault(); form.post('/me/resignation', { preserveScroll: true, onSuccess: () => setResigning(false) }); }}>
            <p className="text-sm text-muted-foreground">{s('resign_hint', { days: String(data.notice_days) })}</p>
            <FormField label={s('last_day')} error={form.errors.last_day}><Input type="date" dir="ltr" value={form.data.last_day} onChange={(event) => form.setData('last_day', event.target.value)} /></FormField>
            <FormField label={s('reason')} error={form.errors.reason ?? (form.errors as Record<string, string>).kind}><Textarea rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
            <div className="flex gap-2">
              <Button type="submit" variant="destructive" disabled={form.processing}>{s('resign_confirm')}</Button>
              <Button type="button" variant="ghost" onClick={() => setResigning(false)}>{s('cancel')}</Button>
            </div>
          </form>
        ) : <Button variant="outline" className="self-start" onClick={() => setResigning(true)}>{s('resign')}</Button>)}
      </CardContent>
    </Card>
  );
}
