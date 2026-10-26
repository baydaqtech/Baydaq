import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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

export interface TrainingItem {
  id: number; company_id: number; title: string; provider: string | null; location: string | null; starts_on: string; ends_on: string | null;
  hours: string | null; cost: string | null; certificate_months: number | null; status: string;
}

export interface AttendeeItem { id: number; employee_id: number; status: string; certificate_number: string | null; certificate_expires_on: string | null }

type EmployeeOption = Option & { company_id: number };

interface Props {
  trainings: (TrainingItem & { company: string | null; can_update: boolean; attendees: (AttendeeItem & { employee: string; can_update: boolean })[] })[];
  expiring: { id: number; employee: string; training: string; expires_on: string; expired: boolean }[];
  companies: Option[];
  employees: EmployeeOption[];
  statuses: string[];
  attendance: string[];
}

function TrainingForm({ companies, onDone }: { companies: Option[]; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.training.${key}`);
  const form = useForm({ company_id: companies[0]?.value ?? '', title: '', provider: '', location: '', starts_on: '', ends_on: '', hours: '', cost: '', certificate_months: '' });
  const text = (field: 'title' | 'provider' | 'location', label: string) => (
    <FormField label={label} error={form.errors[field]}><Input value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} /></FormField>
  );
  const number = (field: 'hours' | 'cost' | 'certificate_months' | 'starts_on' | 'ends_on', type = 'number') => (
    <FormField label={r(field)} error={form.errors[field]}><Input type={type} min="0" dir="ltr" value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} /></FormField>
  );

  return (
    <Card className="mb-4"><CardContent className="py-4">
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/training'), { preserveScroll: true, onSuccess: onDone }); }}>
        <FormField label={r('company')} error={form.errors.company_id}><SelectInput value={String(form.data.company_id)} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>
        {text('title', r('title_field'))}
        {text('provider', r('provider'))}
        {text('location', r('location'))}
        {number('starts_on', 'date')}
        {number('ends_on', 'date')}
        {number('hours')}
        {number('cost')}
        {number('certificate_months')}
        <div className="sm:col-span-3"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>
      </form>
    </CardContent></Card>
  );
}

function Enrol({ training, employees }: { training: Props['trainings'][number]; employees: EmployeeOption[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const enrolled = new Set(training.attendees.map((attendee) => attendee.employee_id));
  const options = employees.filter((employee) => employee.company_id === training.company_id && !enrolled.has(Number(employee.value)));
  const [employeeId, setEmployeeId] = useState(String(options[0]?.value ?? ''));

  if (options.length === 0) {
    return null;
  }

  return (
    <div className="mt-3 flex flex-wrap items-end gap-2">
      <SelectInput className="h-8 w-64" value={employeeId} onChange={setEmployeeId} options={options} />
      <Button size="sm" variant="outline" onClick={() => router.post(url(`/hr/training/${training.id}/attendees`), { employee_ids: [Number(employeeId)] }, { preserveScroll: true })}><Plus data-icon="inline-start" />{t('hr.training.enrol')}</Button>
    </div>
  );
}

/** Training sessions, attendance and certificates to renew. */
export default function TrainingIndex({ trainings, expiring, companies, employees, statuses, attendance }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const r = (key: string, replace?: Record<string, string>) => t(`hr.training.${key}`, replace);
  const [adding, setAdding] = useState(false);

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('lede')} actions={companies.length > 0 && <Button onClick={() => setAdding(!adding)}><Plus data-icon="inline-start" />{r('new')}</Button>} />
      {adding && <TrainingForm companies={companies} onDone={() => setAdding(false)} />}
      <Card className="mb-4">
        <CardHeader><CardTitle className="text-base">{r('expiring')}</CardTitle></CardHeader>
        <CardContent className="divide-y text-sm">
          {expiring.length === 0 && <p className="text-muted-foreground">{r('nothing_expiring')}</p>}
          {expiring.map((item) => (
            <div key={item.id} className="flex items-center justify-between py-1.5">
              <span><span className="font-medium">{item.employee}</span> · {item.training}</span>
              <Badge variant={item.expired ? 'destructive' : 'outline'}>{item.expired ? r('expired') : r('expires', { date: formatDate(item.expires_on, locale) })}</Badge>
            </div>
          ))}
        </CardContent>
      </Card>
      {trainings.length === 0 && <p className="text-sm text-muted-foreground">{r('none')}</p>}
      <div className="flex flex-col gap-4">
        {trainings.map((training) => (
          <Card key={training.id}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                {training.title}
                <span className="text-sm font-normal text-muted-foreground">· {training.company} · {formatDate(training.starts_on, locale)}{training.provider && ` · ${training.provider}`}{training.cost && ` · ${formatMoney(training.cost, locale)}`}</span>
                {training.can_update
                  ? <SelectInput className="ms-auto h-8 w-32" value={training.status} onChange={(status) => router.put(url(`/hr/training/${training.id}`), { title: training.title, provider: training.provider, location: training.location, starts_on: training.starts_on, ends_on: training.ends_on, hours: training.hours, cost: training.cost, certificate_months: training.certificate_months, status }, { preserveScroll: true })} options={statuses.map((value) => ({ value, label: r(`statuses.${value}`) }))} />
                  : <Badge variant="outline" className="ms-auto">{r(`statuses.${training.status}`)}</Badge>}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-sm">
              <ul className="divide-y">
                {training.attendees.map((attendee) => (
                  <li key={attendee.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                    <span>
                      <span className="font-medium">{attendee.employee}</span>
                      {attendee.certificate_expires_on && <span className="text-muted-foreground"> · {r('expires', { date: formatDate(attendee.certificate_expires_on, locale) })}</span>}
                    </span>
                    {attendee.can_update
                      ? <SelectInput className="h-8 w-32" value={attendee.status} onChange={(status) => router.put(url(`/hr/training/attendees/${attendee.id}`), { status, certificate_number: attendee.certificate_number }, { preserveScroll: true })} options={attendance.map((value) => ({ value, label: r(`attendance.${value}`) }))} />
                      : <Badge variant="outline">{r(`attendance.${attendee.status}`)}</Badge>}
                  </li>
                ))}
              </ul>
              {training.can_update && training.status !== 'cancelled' && <Enrol training={training} employees={employees} />}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
