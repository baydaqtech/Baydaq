import { router, useForm } from '@inertiajs/react';
import FormField from '@/components/platform/FormField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import type { OvertimeItem } from '@/Pages/Hr/Overtime/Index';

export interface OvertimeData { hours_this_year: string; max_hours_year: number; items: OvertimeItem[] }

/** My overtime this year and asking to be paid for extra hours. */
export default function MyOvertime({ data }: { data: OvertimeData }) {
  const t = useT();
  const { locale } = useLocale();
  const o = (key: string, replace?: Record<string, string>) => t(`hr.overtime.${key}`, replace);
  const form = useForm({ work_date: '', hours: '', reason: '' });

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{o('mine')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <p className="text-muted-foreground">{data.max_hours_year > 0 ? o('this_year', { hours: data.hours_this_year, max: String(data.max_hours_year) }) : o('this_year_no_cap', { hours: data.hours_this_year })}</p>
        <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); form.post('/me/overtime', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
          <FormField label={o('work_date')} error={form.errors.work_date}><Input type="date" dir="ltr" value={form.data.work_date} onChange={(event) => form.setData('work_date', event.target.value)} /></FormField>
          <FormField label={o('hours')} error={form.errors.hours}><Input type="number" min="0.25" max="12" step="0.25" dir="ltr" className="w-24" value={form.data.hours} onChange={(event) => form.setData('hours', event.target.value)} /></FormField>
          <FormField label={o('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <Button type="submit" disabled={form.processing || form.data.work_date === '' || form.data.hours === ''}>{o('ask')}</Button>
        </form>
        {data.items.length > 0 && (
          <ul className="divide-y">
            {data.items.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                <span>{formatDate(item.work_date, locale)} · <span dir="ltr">{item.hours}</span> {o('hours')}{item.amount && <> · {formatMoney(item.amount, locale)}</>}</span>
                <span className="flex items-center gap-2">
                  <Badge variant="outline">{o(`statuses.${item.status}`)}</Badge>
                  {item.status === 'pending' && <Button size="sm" variant="ghost" onClick={() => router.post(`/me/overtime/${item.id}/cancel`, {}, { preserveScroll: true })}>{o('cancel')}</Button>}
                </span>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
