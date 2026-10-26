import { useForm } from '@inertiajs/react';
import FormField from '@/components/platform/FormField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface RemoteData {
  work_mode: string;
  remote_days: number[];
  remote_today: boolean;
  requests: { id: number; work_date: string; reason: string | null; status: string }[];
}

/** My work-from-home pattern, whether today is a remote day, and asking for a day at home. */
export default function MyRemote({ data }: { data: RemoteData }) {
  const t = useT();
  const { locale } = useLocale();
  const r = (key: string, replace?: Record<string, string>) => t(`hr.remote.${key}`, replace);
  const form = useForm({ work_date: '', reason: '' });
  const mode = r(`modes.${data.work_mode}`) + (data.work_mode === 'hybrid' ? ` (${data.remote_days.map((day) => t(`hr.remote.days.${day}`)).join('، ')})` : '');

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{r('mine')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <p>{r('my_pattern', { mode })}</p>
        {data.remote_today && <p className="rounded bg-emerald-50 p-2 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{r('remote_today')}</p>}
        {data.work_mode !== 'remote' && (
          <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); form.post('/me/remote', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
            <FormField label={r('work_date')} error={form.errors.work_date}><Input type="date" dir="ltr" value={form.data.work_date} onChange={(event) => form.setData('work_date', event.target.value)} /></FormField>
            <FormField label={r('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
            <Button type="submit" disabled={form.processing || form.data.work_date === ''}>{r('ask')}</Button>
          </form>
        )}
        {data.requests.length > 0 && (
          <ul className="divide-y">
            {data.requests.map((request) => (
              <li key={request.id} className="flex items-center justify-between py-1.5">
                <span>{formatDate(request.work_date, locale)}{request.reason && <span className="text-muted-foreground"> · {request.reason}</span>}</span>
                <Badge variant="outline">{r(`statuses.${request.status}`)}</Badge>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
