import { router, useForm } from '@inertiajs/react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface OvertimeItem { id: number; employee_id: number; work_date: string; hours: string; reason: string | null; source: string; status: string; amount: string | null }

interface Props {
  items: (OvertimeItem & { employee: string; can_decide: boolean })[];
  employees: Option[];
  status: string;
  statuses: string[];
  rules: { extra_percent: number; max_hours_year: number; hours_per_day: number };
}

/** Overtime waiting for a decision, recording hours for someone, and what was approved. */
export default function OvertimeIndex({ items, employees, status, statuses, rules }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const o = (key: string, replace?: Record<string, string>) => t(`hr.overtime.${key}`, replace);
  const form = useForm({ employee_id: employees[0]?.value ?? '', work_date: '', hours: '', reason: '' });
  const decide = (id: number, approve: boolean) => router.put(url(`/hr/overtime/${id}`), { approve }, { preserveScroll: true });

  return (
    <SuiteLayout title={o('title')}>
      <PageHeader title={o('title')} description={o('lede', { extra: String(rules.extra_percent), max: String(rules.max_hours_year) })} />
      {employees.length > 0 && (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{o('record')}</CardTitle></CardHeader>
          <CardContent>
            <form className="grid gap-3 sm:grid-cols-5" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/overtime'), { preserveScroll: true, onSuccess: () => form.reset('work_date', 'hours', 'reason') }); }}>
              <FormField label={o('employee')} error={form.errors.employee_id} className="sm:col-span-2"><SelectInput value={String(form.data.employee_id)} onChange={(value) => form.setData('employee_id', Number(value))} options={employees} /></FormField>
              <FormField label={o('work_date')} error={form.errors.work_date}><Input type="date" dir="ltr" value={form.data.work_date} onChange={(event) => form.setData('work_date', event.target.value)} /></FormField>
              <FormField label={o('hours')} error={form.errors.hours}><Input type="number" min="0.25" max="12" step="0.25" dir="ltr" value={form.data.hours} onChange={(event) => form.setData('hours', event.target.value)} /></FormField>
              <FormField label={o('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
              <div className="sm:col-span-5"><Button type="submit" disabled={form.processing}>{o('record')}</Button></div>
            </form>
          </CardContent>
        </Card>
      )}
      <div className="mb-3 flex flex-wrap gap-1">
        {[...statuses, 'all'].map((value) => (
          <Button key={value} size="sm" variant={value === status ? 'default' : 'outline'} onClick={() => router.get(url('/hr/overtime'), { status: value }, { preserveState: true })}>{o(`statuses.${value}`)}</Button>
        ))}
      </div>
      <Card>
        <CardContent className="divide-y text-sm">
          {items.length === 0 && <p className="py-3 text-muted-foreground">{o('none')}</p>}
          {items.map((item) => (
            <div key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>
                <span className="font-medium">{item.employee}</span> · {formatDate(item.work_date, locale)} · <span dir="ltr">{item.hours}</span> {o('hours')}
                {item.amount && <> · {formatMoney(item.amount, locale)}</>}
                {item.reason && <span className="text-muted-foreground"> · {item.reason}</span>}
                <span className="block text-xs text-muted-foreground">{o(`sources.${item.source}`)}</span>
              </span>
              {item.status === 'pending' && item.can_decide
                ? <span className="flex gap-1"><Button size="sm" onClick={() => decide(item.id, true)}>{o('approve')}</Button><Button size="sm" variant="ghost" onClick={() => decide(item.id, false)}>{o('reject')}</Button></span>
                : <Badge variant="outline">{o(`statuses.${item.status}`)}</Badge>}
            </div>
          ))}
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
