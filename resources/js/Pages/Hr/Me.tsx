import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AdvanceCalculator, { type AdvanceData } from '@/components/hr/AdvanceCalculator';
import LeaveBalanceCard, { type LeaveBalance } from '@/components/hr/LeaveBalanceCard';
import MyBreak, { type BreakState } from '@/components/hr/MyBreak';
import MyShifts, { type ShiftsData } from '@/components/hr/MyShifts';
import MyExpenses, { type ExpensesData } from '@/components/hr/MyExpenses';
import MyRecord, { type RecordData } from '@/components/hr/MyRecord';
import { MyInsuranceCard, MyTrainingCard, type MyInsurance, type MyTrainings } from '@/components/hr/MyBenefits';
import MyOvertime, { type OvertimeData } from '@/components/hr/MyOvertime';
import MyRemote, { type RemoteData } from '@/components/hr/MyRemote';
import MyReviews, { type MyReview } from '@/components/hr/MyReviews';
import MyTasks, { type MyTask } from '@/components/hr/MyTasks';
import PinCard from '@/components/hr/PinCard';
import BrandMark from '@/components/platform/BrandMark';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import StatusMessage from '@/components/platform/StatusMessage';
import UserMenu from '@/components/platform/UserMenu';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PlatformShell from '@/layouts/PlatformShell';
import { formatDate, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import type { LeaveItem } from '@/components/hr/LeaveTab';
import type { Option } from '@/types/resources';

interface Props {
  employee: { name: string; employee_number: string; job: string | null; branch: string | null; company: string | null; hired_on: string | null };
  balances: LeaveBalance[];
  requests: LeaveItem[];
  types: Option[];
  advance: AdvanceData;
  shifts: ShiftsData;
  has_pin: boolean;
  tasks: MyTask[];
  break_state: BreakState;
  record: RecordData;
  reviews: MyReview[];
  remote: RemoteData;
  expenses: ExpensesData;
  overtime: OvertimeData;
  insurance: MyInsurance;
  trainings: MyTrainings;
  features: string[];
}

/** An employee's own page: who they are, how much leave is left (with the working), and asking for more. */
export default function Me({ employee, balances, requests, types, advance, shifts, has_pin: hasPin, tasks, break_state: breakState, record, reviews, remote, expenses, overtime, insurance, trainings, features }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const [asking, setAsking] = useState(false);
  const form = useForm({ leave_type_id: types[0]?.value ?? '', starts_on: '', ends_on: '', reason: '' });
  const f = (field: string) => t(`hr.leave.fields.${field}`);
  const errors = form.errors as Record<string, string>;

  return (
    <PlatformShell>
      <Head title={t('hr.me.title')} />
      <header className="border-b bg-background/90">
        <div className="mx-auto flex h-14 max-w-4xl items-center gap-3 px-4">
          <BrandMark className="size-7 text-walnut" />
          <span className="font-semibold">{t('hr.me.title')}</span>
          <div className="ms-auto"><UserMenu /></div>
        </div>
      </header>
      <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-6">
        <section>
          <h1 className="text-xl font-semibold">{employee.name}</h1>
          <p className="text-sm text-muted-foreground">{[employee.job, employee.branch, employee.company].filter(Boolean).join(' · ')} · <span dir="ltr">{employee.employee_number}</span></p>
          {employee.hired_on && <p className="text-xs text-muted-foreground">{t('hr.me.since', { date: formatDate(employee.hired_on, locale) })}</p>}
        </section>

        <section className="flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-semibold">{t('hr.me.balance')}</h2>
            <Button size="sm" onClick={() => setAsking(!asking)}><Plus data-icon="inline-start" />{t('hr.leave.new')}</Button>
          </div>
          {balances.length === 0 ? <StatusMessage>{t('hr.me.no_balance')}</StatusMessage> : (
            <div className="grid gap-3 sm:grid-cols-2">{balances.map((balance) => <LeaveBalanceCard key={balance.type_id} balance={balance} />)}</div>
          )}
          {asking && (
            <Card>
              <CardContent className="py-4">
                <form onSubmit={(event) => { event.preventDefault(); form.post('/me/leave', { preserveScroll: true, onSuccess: () => { setAsking(false); form.reset(); } }); }} className="grid gap-3 sm:grid-cols-2">
                  <FormField label={f('type')} error={form.errors.leave_type_id}><SelectInput value={form.data.leave_type_id} onChange={(value) => form.setData('leave_type_id', Number(value))} options={types} /></FormField>
                  <FormField label={f('reason')} error={form.errors.reason}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
                  <FormField label={f('starts_on')} error={form.errors.starts_on}><Input type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
                  <FormField label={f('ends_on')} error={form.errors.ends_on ?? errors.days}><Input type="date" dir="ltr" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} /></FormField>
                  <div className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>
                </form>
              </CardContent>
            </Card>
          )}
        </section>

        {features.includes('hr.breaks') && <MyBreak state={breakState} />}

        {features.includes('hr.tasks') && <MyTasks tasks={tasks} />}

        {features.includes('hr.shifts') && <MyShifts data={shifts} />}

        {features.includes('hr.advances') && <AdvanceCalculator data={advance} />}
        {features.includes('hr.advances') && features.includes('hr.loans') && <AdvanceCalculator data={advance.loan} kind="loan" />}
        {features.includes('hr.overtime') && <MyOvertime data={overtime} />}
        {features.includes('hr.insurance') && <MyInsuranceCard members={insurance} />}
        {features.includes('hr.training') && <MyTrainingCard trainings={trainings} />}
        {features.includes('hr.expenses') && <MyExpenses data={expenses} />}
        {features.includes('hr.remote') && <MyRemote data={remote} />}
        {features.includes('hr.performance') && <MyReviews reviews={reviews} />}
        <MyRecord data={record} canResign={features.includes('hr.offboarding')} />

        <PinCard hasPin={hasPin} />

        <Card>
          <CardHeader><CardTitle className="text-base">{t('hr.leave.title')}</CardTitle></CardHeader>
          <CardContent>
            <ul className="divide-y text-sm">
              {requests.length === 0 && <li className="py-3 text-muted-foreground">{t('hr.leave.none')}</li>}
              {requests.map((item) => (
                <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <span><span className="font-medium">{item.type}</span> <span className="text-muted-foreground">· {formatDate(item.starts_on, locale)} → {formatDate(item.ends_on, locale)} · {formatNumber(item.days, locale)} {t('hr.leave.balance_card.days')}</span></span>
                  <StatusBadge group="leave_status" value={item.status} file="hr" />
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </main>
    </PlatformShell>
  );
}
