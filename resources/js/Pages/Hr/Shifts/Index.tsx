import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Moon, Plus, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface ShiftRow {
  id: number; date: string; branch_id: number; branch: string; starts_at: string; ends_at: string; minutes: number;
  employee: string | null; employee_id: number | null; away: boolean; job: string | null; is_open: boolean; on_holiday: boolean; ramadan_hours: boolean; notes: string | null; break_at: string | null; break_minutes: number | null;
}

interface HolidayRow { id: number; name: string; kind: 'public' | 'ramadan'; starts_on: string; ends_on: string }

interface Props {
  week: string;
  days: { date: string; holiday: string | null; ramadan: boolean }[];
  shifts: ShiftRow[];
  branches: Option[];
  employees: Option[];
  jobs: Option[];
  holidays: HolidayRow[];
  ramadan_hours: number;
  saudi_years: number[];
}

function ShiftDialog({ shift, date, branches, employees, jobs }: { shift?: ShiftRow; date?: string; branches: Option[]; employees: Option[]; jobs: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`hr.shifts.fields.${field}`);
  const form = useForm({
    branch_id: String(shift?.branch_id ?? branches[0]?.value ?? ''),
    shift_date: shift?.date ?? date ?? '',
    starts_at: shift?.starts_at ?? '09:00',
    ends_at: shift?.ends_at ?? '17:00',
    employee_id: shift?.employee_id ? String(shift.employee_id) : '',
    job_id: '',
    allow_holiday: shift?.on_holiday ?? false,
    repeat_days: '1',
    notes: shift?.notes ?? '',
    break_at: shift?.break_at ?? '',
    break_minutes: String(shift?.break_minutes ?? 30),
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, employee_id: data.employee_id || null, job_id: data.job_id || null, is_open: data.employee_id === '', break_at: data.break_at || null, break_minutes: data.break_at ? Number(data.break_minutes) : null }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    shift ? form.put(url(`/hr/shifts/${shift.id}`), options) : form.post(url('/hr/shifts'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {shift ? <button type="button" className="text-start text-xs underline-offset-2 hover:underline">{t('common.actions.edit')}</button> : <Button size="icon-sm" variant="ghost" aria-label={t('hr.shifts.new')}><Plus /></Button>}
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{shift ? t('hr.shifts.edit') : t('hr.shifts.new')}</DialogTitle><DialogDescription>{t('hr.shifts.open_hint')}</DialogDescription></DialogHeader>
          <FormField label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>
          <FormField label={f('date')} error={form.errors.shift_date}><Input type="date" dir="ltr" value={form.data.shift_date} onChange={(event) => form.setData('shift_date', event.target.value)} /></FormField>
          <FormField label={f('starts_at')} error={form.errors.starts_at}><Input type="time" dir="ltr" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} /></FormField>
          <FormField label={f('ends_at')} error={form.errors.ends_at}><Input type="time" dir="ltr" value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} /></FormField>
          <FormField label={f('employee')} error={form.errors.employee_id}><SelectInput value={form.data.employee_id} onChange={(value) => form.setData('employee_id', value)} options={employees} emptyLabel={t('hr.shifts.nobody')} /></FormField>
          {form.data.employee_id === '' && <FormField label={t('hr.shifts.only_job')} error={form.errors.job_id}><SelectInput value={form.data.job_id} onChange={(value) => form.setData('job_id', value)} options={jobs} emptyLabel={t('hr.shifts.any_job')} /></FormField>}
          {!shift && <FormField label={t('hr.shifts.repeat_days')} error={form.errors.repeat_days}><Input type="number" min="1" max="14" dir="ltr" value={form.data.repeat_days} onChange={(event) => form.setData('repeat_days', event.target.value)} /></FormField>}
          <FormField label={t('hr.breaks.shift_break_at')} hint={t('hr.breaks.shift_break_hint')} error={form.errors.break_at}><Input type="time" dir="ltr" value={form.data.break_at} onChange={(event) => form.setData('break_at', event.target.value)} /></FormField>
          <FormField label={t('hr.breaks.minutes')} error={form.errors.break_minutes}><Input type="number" min="5" max="120" dir="ltr" value={form.data.break_minutes} disabled={!form.data.break_at} onChange={(event) => form.setData('break_minutes', event.target.value)} /></FormField>
          <FormField label={f('notes')} error={form.errors.notes} className="sm:col-span-2"><Input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></FormField>
          <label className="flex items-center gap-2 text-sm sm:col-span-2"><Checkbox checked={form.data.allow_holiday} onCheckedChange={(state) => form.setData('allow_holiday', state === true)} />{t('hr.shifts.allow_holiday')}</label>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function HolidaysCard({ holidays, saudiYears, ramadanHours }: { holidays: HolidayRow[]; saudiYears: number[]; ramadanHours: number }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const form = useForm({ name_ar: '', name_en: '', kind: 'public', starts_on: '', ends_on: '' });
  const f = (field: string) => t(`hr.shifts.fields.${field}`);

  return (
    <Card className="mt-6">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle className="text-base">{t('hr.shifts.holidays')}</CardTitle>
        <div className="flex flex-wrap gap-2">
          {can('hr.shifts.update') && saudiYears.map((year) => <ConfirmAction key={year} href={url('/hr/holidays/load')} method="post" data={{ year }} size="sm" variant="outline" title={t('hr.shifts.load_saudi', { year: String(year) })}>{t('hr.shifts.load_saudi', { year: String(year) })}</ConfirmAction>)}
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        {ramadanHours > 0 && <p className="text-muted-foreground">{t('hr.shifts.ramadan_cap', { hours: String(ramadanHours) })}</p>}
        <ul className="divide-y">
          {holidays.map((holiday) => (
            <li key={holiday.id} className="flex items-center justify-between gap-2 py-2">
              <span>{holiday.kind === 'ramadan' && <Moon className="me-1 inline size-4" />}{holiday.name} · <span className="text-muted-foreground">{formatDate(holiday.starts_on, locale)} → {formatDate(holiday.ends_on, locale)}</span></span>
              {can('hr.shifts.update') && <ConfirmAction href={url(`/hr/holidays/${holiday.id}`)} method="delete" size="icon-sm" variant="ghost" title={t('common.actions.remove')}><Trash2 /></ConfirmAction>}
            </li>
          ))}
        </ul>
        {can('hr.shifts.update') && (
          <form onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/holidays'), { preserveScroll: true, onSuccess: () => form.reset() }); }} className="grid gap-2 sm:grid-cols-6">
            <FormField label={f('name_ar')} error={form.errors.name_ar}><Input dir="rtl" value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
            <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
            <FormField label={f('kind')}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={[{ value: 'public', label: t('hr.shifts.kind.public') }, { value: 'ramadan', label: t('hr.shifts.kind.ramadan') }]} /></FormField>
            <FormField label={f('starts_on')} error={form.errors.starts_on}><Input type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
            <FormField label={f('ends_on')} error={form.errors.ends_on}><Input type="date" dir="ltr" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} /></FormField>
            <div className="flex items-end"><Button type="submit" size="sm" disabled={form.processing}>{t('common.actions.add_row')}</Button></div>
          </form>
        )}
      </CardContent>
    </Card>
  );
}

/** The week's roster across the branches in reach, with open shifts and the holiday calendar (H2, H9). */
export default function ShiftsIndex({ week, days, shifts, branches, employees, jobs, holidays, ramadan_hours: ramadanHours, saudi_years: saudiYears }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const shift = (offset: number) => { const date = new Date(`${week}T00:00:00`); date.setDate(date.getDate() + offset); return date.toISOString().slice(0, 10); };

  return (
    <SuiteLayout title={t('hr.shifts.title')}>
      <PageHeader
        title={t('hr.shifts.title')}
        description={t('hr.shifts.week_of', { date: formatDate(week, locale) })}
        actions={(
          <div className="flex gap-1">
            <Button asChild size="sm" variant="outline"><Link href={url(`/hr/shifts?week=${shift(-7)}`)} preserveScroll><ChevronRight className="rtl:hidden" /><ChevronLeft className="hidden rtl:block" />{t('hr.shifts.previous_week')}</Link></Button>
            <Button asChild size="sm" variant="outline"><Link href={url(`/hr/shifts?week=${shift(7)}`)} preserveScroll>{t('hr.shifts.next_week')}<ChevronLeft className="rtl:hidden" /><ChevronRight className="hidden rtl:block" /></Link></Button>
          </div>
        )}
      />
      <div className="grid gap-3 md:grid-cols-7">
        {days.map((day) => (
          <Card key={day.date} className={cn('gap-2 py-3', day.holiday && 'border-red-200 bg-red-50/50', day.ramadan && 'border-indigo-200')}>
            <CardHeader className="flex flex-row items-center justify-between px-3">
              <CardTitle className="text-sm">{new Date(`${day.date}T00:00:00`).toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' })}</CardTitle>
              {can('hr.shifts.create') && <ShiftDialog date={day.date} branches={branches} employees={employees} jobs={jobs} />}
            </CardHeader>
            <CardContent className="flex flex-col gap-2 px-3 text-xs">
              {day.holiday && <Badge variant="outline" className="w-fit border-transparent bg-red-100 text-red-800">{day.holiday}</Badge>}
              {day.ramadan && <Badge variant="outline" className="w-fit border-transparent bg-indigo-100 text-indigo-800"><Moon className="size-3" />{t('hr.shifts.ramadan')}</Badge>}
              {shifts.filter((row) => row.date === day.date).map((row) => (
                <div key={row.id} className={cn('rounded-md border p-2', row.is_open && 'border-dashed border-brass bg-brass/5')}>
                  <div className="flex items-center justify-between gap-1">
                    <span dir="ltr" className="font-medium">{row.starts_at}–{row.ends_at}{row.break_at && <span className="ms-1 text-xs font-normal text-muted-foreground">☕ {row.break_at}</span>}</span>
                    {can('hr.shifts.delete') && <ConfirmAction href={url(`/hr/shifts/${row.id}`)} method="delete" size="icon-sm" variant="ghost" title={t('common.actions.remove')}><Trash2 /></ConfirmAction>}
                  </div>
                  <div>{row.employee ?? <span className="text-brass">{t('hr.shifts.open')}{row.job && ` · ${row.job}`}</span>}</div>
                  <div className="text-muted-foreground">{row.branch}</div>
                  {row.away && <Badge variant="outline" className="mt-1">{t('hr.shifts.away')}</Badge>}
                  {can('hr.shifts.update') && <ShiftDialog shift={row} branches={branches} employees={employees} jobs={jobs} />}
                </div>
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
      <HolidaysCard holidays={holidays} saudiYears={saudiYears} ramadanHours={ramadanHours} />
    </SuiteLayout>
  );
}
