import { Link, router } from '@inertiajs/react';
import { House } from 'lucide-react';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface EmployeeRow {
  id: number; name: string; number: string; work_mode: string; remote_days: number[]; remote_today: boolean;
  clocked_in: boolean; clocked_in_remote: boolean; can_update: boolean;
}

interface RequestRow { id: number; employee_id: number; employee: string; work_date: string; reason: string | null; status: string; can_decide: boolean }

/** Who works from home today, each person's pattern, and requests for a day at home. */
export default function RemoteIndex({ employees, requests, modes }: { employees: EmployeeRow[]; requests: RequestRow[]; modes: string[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const r = (key: string, replace?: Record<string, string>) => t(`hr.remote.${key}`, replace);
  const days = [0, 1, 2, 3, 4, 5, 6];
  const savePattern = (employee: EmployeeRow, workMode: string, remoteDays: number[]) => router.put(url(`/hr/employees/${employee.id}/work-pattern`), { work_mode: workMode, remote_days: remoteDays }, { preserveScroll: true });
  const today = employees.filter((employee) => employee.remote_today);

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('lede')} />
      <div className="mb-4 grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><House className="size-4" />{r('today')}</CardTitle></CardHeader>
          <CardContent className="divide-y text-sm">
            {today.length === 0 && <p className="text-muted-foreground">{r('nobody')}</p>}
            {today.map((employee) => (
              <div key={employee.id} className="flex items-center justify-between py-1.5">
                <Link href={url(`/hr/employees/${employee.id}`)} className="hover:underline">{employee.name}</Link>
                <Badge variant={employee.clocked_in ? 'default' : 'outline'}>{employee.clocked_in ? (employee.clocked_in_remote ? r('clocked_in_remote') : r('clocked_in')) : r('not_in')}</Badge>
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{r('requests')}</CardTitle></CardHeader>
          <CardContent className="divide-y text-sm">
            {requests.length === 0 && <p className="text-muted-foreground">{r('no_requests')}</p>}
            {requests.map((request) => (
              <div key={request.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                <span><span className="font-medium">{request.employee}</span> · {formatDate(request.work_date, locale)}{request.reason && <span className="text-muted-foreground"> · {request.reason}</span>}</span>
                {request.status === 'pending' && request.can_decide
                  ? <span className="flex gap-1">
                      <Button size="sm" onClick={() => router.put(url(`/hr/remote/${request.id}`), { approve: true }, { preserveScroll: true })}>{r('approve')}</Button>
                      <Button size="sm" variant="ghost" onClick={() => router.put(url(`/hr/remote/${request.id}`), { approve: false }, { preserveScroll: true })}>{r('reject')}</Button>
                    </span>
                  : <Badge variant="outline">{r(`statuses.${request.status}`)}</Badge>}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">{r('patterns')}</CardTitle></CardHeader>
        <CardContent className="divide-y">
          {employees.map((employee) => (
            <div key={employee.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
              <Link href={url(`/hr/employees/${employee.id}`)} className="min-w-40 font-medium hover:underline">{employee.name} <span className="text-xs font-normal text-muted-foreground" dir="ltr">{employee.number}</span></Link>
              <div className="flex flex-wrap items-center gap-2">
                {employee.can_update
                  ? <SelectInput className="h-8 w-36" value={employee.work_mode} onChange={(mode) => savePattern(employee, mode, employee.remote_days)} options={modes.map((value) => ({ value, label: r(`modes.${value}`) }))} />
                  : <Badge variant="outline">{r(`modes.${employee.work_mode}`)}</Badge>}
                {employee.work_mode === 'hybrid' && (
                  <>
                    <div className="flex gap-1">
                      {days.map((day) => {
                        const remote = employee.remote_days.includes(day);

                        return (
                          <button
                            key={day}
                            type="button"
                            disabled={!employee.can_update}
                            onClick={() => savePattern(employee, 'hybrid', remote ? employee.remote_days.filter((item) => item !== day) : [...employee.remote_days, day])}
                            className={cn('rounded-md border px-2 py-1 text-xs', remote ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted')}
                          >
                            {t(`hr.remote.days.${day}`)}
                          </button>
                        );
                      })}
                    </div>
                    <span className="text-xs text-muted-foreground">{r('remote_days_count', { count: String(employee.remote_days.length) })}</span>
                  </>
                )}
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
