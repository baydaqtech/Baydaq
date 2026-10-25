import { Link, router, useForm } from '@inertiajs/react';
import { Clock, Gavel, Plus } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option } from '@/types/resources';

interface Violation {
  id: number; employee_id: number; employee: string; employee_number: string; category: string; occurred_on: string; minutes: number | null; occurrence: number;
  description: string | null; penalty: string; deduction_days: string | null; status: string; employee_response: string | null; notified_at: string | null; source: string;
}

interface Late { employee_id: number; employee: string | null; date: string; starts_at: string; punched_at: string; minutes: number }

interface Props {
  violations: Violation[];
  by_category: Record<string, number>;
  by_employee: { employee_id: number; employee: string; count: number; deduction_days: string }[];
  late: Late[];
  filters: Filters;
  categories: string[];
  penalties: string[];
  deduction_types: Option[];
  can_record: boolean;
  can_decide: boolean;
  employees: Option[];
  grace_minutes: number;
}

function RecordDialog({ employees, categories }: { employees: Option[]; categories: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const v = (key: string) => t(`hr.violations.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ employee_id: '', category: 'late', occurred_on: new Date().toISOString().slice(0, 10), minutes: '', description: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus />{v('record')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{v('record')}</DialogTitle></DialogHeader>
        <form
          id="record-violation"
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event) => { event.preventDefault(); form.transform(({ employee_id: _employee, ...data }) => data); form.post(url(`/hr/employees/${form.data.employee_id}/violations`), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}
        >
          <FormField label={v('employee')} className="sm:col-span-2"><SelectInput value={form.data.employee_id} onChange={(value) => form.setData('employee_id', value)} options={employees} /></FormField>
          <FormField label={v('category')} error={form.errors.category}><SelectInput value={form.data.category} onChange={(value) => form.setData('category', value)} options={categories.map((value) => ({ value, label: v(`categories.${value}`) }))} /></FormField>
          <FormField label={v('occurred_on')} error={form.errors.occurred_on}><Input type="date" dir="ltr" value={form.data.occurred_on} onChange={(event) => form.setData('occurred_on', event.target.value)} /></FormField>
          <FormField label={v('minutes')} error={form.errors.minutes}><Input type="number" min="1" dir="ltr" value={form.data.minutes} onChange={(event) => form.setData('minutes', event.target.value)} /></FormField>
          <FormField label={v('description')} error={form.errors.description} className="sm:col-span-2"><Textarea rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="record-violation" disabled={form.processing || form.data.employee_id === ''}>{v('record')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function DecideDialog({ violation, penalties, deductionTypes }: { violation: Violation; penalties: string[]; deductionTypes: Option[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const v = (key: string) => t(`hr.violations.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ action: 'close', penalty: 'warning', deduction_days: '1', deduction_type_id: String(deductionTypes[0]?.value ?? ''), pay_month: new Date().toISOString().slice(0, 10) });
  const errors = form.errors as Record<string, string>;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Gavel />{v('decide')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{v('decide')} · {violation.employee}</DialogTitle></DialogHeader>
        <form id={`decide-${violation.id}`} className="grid gap-3" onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/violations/${violation.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
          {violation.employee_response && <p className="rounded bg-muted p-2 text-sm"><span className="font-medium">{v('response')}: </span>{violation.employee_response}</p>}
          <FormField label={v('penalty')} error={errors.penalty}><SelectInput value={form.data.penalty} onChange={(value) => form.setData('penalty', value)} options={penalties.map((value) => ({ value, label: v(`penalties.${value}`) }))} /></FormField>
          {form.data.penalty === 'deduction' && (
            <div className="grid gap-3 sm:grid-cols-2">
              <FormField label={v('deduction_days')} error={errors.deduction_days ?? errors.amount}><Input type="number" step="0.25" min="0.25" max="5" dir="ltr" value={form.data.deduction_days} onChange={(event) => form.setData('deduction_days', event.target.value)} /></FormField>
              <FormField label={v('deduction_type')} error={errors.deduction_type_id}><SelectInput value={form.data.deduction_type_id} onChange={(value) => form.setData('deduction_type_id', value)} options={deductionTypes} /></FormField>
            </div>
          )}
        </form>
        <DialogFooter><Button type="submit" form={`decide-${violation.id}`} disabled={form.processing}>{v('decide')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** The violations report: by kind and person for the period, late arrivals to record, and each case. */
export default function ViolationsIndex(props: Props) {
  const { violations, by_category: byCategory, by_employee: byEmployee, late, categories, penalties, deduction_types: deductionTypes, can_record: canRecord, can_decide: canDecide, employees } = props;
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(props.filters);
  const v = (key: string, replace?: Record<string, string>) => t(`hr.violations.${key}`, replace);
  const act = (violation: Violation, action: string) => router.put(url(`/hr/violations/${violation.id}`), { action }, { preserveScroll: true });

  return (
    <SuiteLayout title={v('title')}>
      <PageHeader title={v('title')} description={v('lede')} actions={canRecord && employees.length > 0 ? <RecordDialog employees={employees} categories={categories} /> : null} />
      <div className="mb-4 flex flex-wrap items-end gap-2">
        <FormField label={v('from')}><Input type="date" dir="ltr" value={String(filters.from ?? '')} onChange={(event) => setFilter('from', event.target.value)} /></FormField>
        <FormField label={v('to')}><Input type="date" dir="ltr" value={String(filters.to ?? '')} onChange={(event) => setFilter('to', event.target.value)} /></FormField>
        <div className="w-48"><SelectInput value={String(filters.category ?? '')} onChange={(value) => setFilter('category', value)} emptyLabel={`${v('category')}: ${t('common.actions.all')}`} options={categories.map((value) => ({ value, label: v(`categories.${value}`) }))} /></div>
      </div>
      <div className="mb-4 grid gap-3 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{v('by_category')}</CardTitle></CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            {Object.keys(byCategory).length === 0 && <p className="text-sm text-muted-foreground">{v('none')}</p>}
            {Object.entries(byCategory).map(([category, count]) => <Badge key={category} variant="outline" className="text-sm">{v(`categories.${category}`)}: {count}</Badge>)}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{v('top')}</CardTitle></CardHeader>
          <CardContent className="divide-y text-sm">
            {byEmployee.map((row) => (
              <div key={row.employee_id} className="flex items-center justify-between py-1.5">
                <Link href={url(`/hr/employees/${row.employee_id}`)} className="hover:underline">{row.employee}</Link>
                <span>{row.count}{Number(row.deduction_days) > 0 && <span className="text-muted-foreground"> · {row.deduction_days} {v('deduction_days')}</span>}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
      {canRecord && late.length > 0 && (
        <Card className="mb-4">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base"><Clock className="size-4" />{v('late_found')}</CardTitle>
            <p className="text-xs text-muted-foreground">{v('late_hint', { grace: String(props.grace_minutes) })}</p>
          </CardHeader>
          <CardContent className="divide-y text-sm">
            {late.map((row) => (
              <div key={`${row.employee_id}-${row.date}`} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                <span><span className="font-medium">{row.employee}</span> · {formatDate(row.date, locale)} · {v('late_line', { start: row.starts_at, punch: row.punched_at, minutes: String(row.minutes) })}</span>
                <Button size="sm" variant="outline" onClick={() => router.post(url(`/hr/employees/${row.employee_id}/violations`), { category: 'late', occurred_on: row.date, minutes: row.minutes, source: 'attendance' }, { preserveScroll: true })}>{v('add_late')}</Button>
              </div>
            ))}
          </CardContent>
        </Card>
      )}
      <Card>
        <CardContent className="divide-y">
          {violations.length === 0 && <p className="py-2 text-sm text-muted-foreground">{v('none')}</p>}
          {violations.map((violation) => (
            <div key={violation.id} className="flex flex-col gap-1 py-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <Link href={url(`/hr/employees/${violation.employee_id}`)} className="font-medium hover:underline">{violation.employee}</Link>
                  <Badge variant="outline">{v(`categories.${violation.category}`)}</Badge>
                  <span className="text-muted-foreground">{formatDate(violation.occurred_on, locale)}{violation.minutes ? ` · ${violation.minutes} ${v('minutes')}` : ''}</span>
                  <Badge variant={violation.occurrence > 1 ? 'destructive' : 'secondary'}>{v('occurrence_n', { n: String(violation.occurrence) })}</Badge>
                </div>
                <div className="flex items-center gap-2">
                  <Badge>{v(`statuses.${violation.status}`)}</Badge>
                  {violation.status === 'closed' && <span>{v(`penalties.${violation.penalty}`)}{violation.deduction_days ? ` (${violation.deduction_days})` : ''}</span>}
                </div>
              </div>
              {violation.description && <p className="text-muted-foreground">{violation.description}</p>}
              {violation.status === 'notified' && <p className="text-xs">{v('response')}: {violation.employee_response ?? <span className="text-muted-foreground">{v('no_response')}</span>}</p>}
              {canDecide && ['open', 'notified'].includes(violation.status) && (
                <div className="flex gap-2">
                  {violation.status === 'open' && <Button size="sm" variant="outline" onClick={() => act(violation, 'notify')}>{v('notify')}</Button>}
                  <DecideDialog violation={violation} penalties={penalties} deductionTypes={deductionTypes} />
                  <Button size="sm" variant="ghost" onClick={() => act(violation, 'cancel')}>{v('cancel')}</Button>
                </div>
              )}
            </div>
          ))}
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
