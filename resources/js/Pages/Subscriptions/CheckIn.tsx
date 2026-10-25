import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, CircleX } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface Recent { id: number; time: string; member_id: number; member: string; number: string; allowed: boolean; reason: string | null; [key: string]: unknown }
interface Outcome { result: string; allowed: boolean; member: { id: number; name: string; number: string } | null; membership: { ends_on: string; sessions_left: number | null } | null }

/** The front desk: a code field that keeps focus (barcode scanners type and press Enter) and a big yes/no. */
export default function CheckIn({ branches, branch_id: branchId, zones, can_check_in: canCheckIn, recent }: { branches: Option[]; branch_id: number | null; zones: Option[]; can_check_in: boolean; recent: Recent[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const flash = usePage().flash as { check_in?: Outcome };
  const [outcome, setOutcome] = useState<Outcome | null>(flash.check_in ?? null);
  const input = useRef<HTMLInputElement>(null);
  const form = useForm({ branch_id: branchId, code: '', zone_id: zones[0]?.value ?? '' });

  useEffect(() => input.current?.focus(), [outcome]);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(url('/subscriptions/check-in'), {
      preserveScroll: true,
      onSuccess: (page) => { setOutcome((page.flash as typeof flash).check_in ?? null); form.reset('code'); },
    });
  }

  if (branchId === null) {
    return <SuiteLayout title={t('subscriptions.check_in.title')}><StatusMessage>{t('subscriptions.check_in.no_branch')}</StatusMessage></SuiteLayout>;
  }

  return (
    <SuiteLayout title={t('subscriptions.check_in.title')}>
      <PageHeader title={t('subscriptions.check_in.title')} actions={branches.length > 1 && <SelectInput className="w-48" value={branchId} onChange={(value) => router.get(window.location.pathname, { branch: value })} options={branches} />} />
      <div className="grid gap-4 lg:grid-cols-[1fr_1fr]">
        <div className="flex flex-col gap-4">
          {canCheckIn && (
            <form onSubmit={submit} className="flex gap-2">
              {zones.length > 0 && <SelectInput className="h-12 w-44" value={form.data.zone_id} onChange={(value) => form.setData('zone_id', value)} options={zones} />}
              <Input ref={input} dir="ltr" autoFocus autoComplete="off" className="h-12 text-lg" placeholder={t('subscriptions.check_in.code')} aria-label={t('subscriptions.check_in.code')} value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
              <Button type="submit" size="lg" className="h-12" disabled={form.processing || !form.data.code}>{t('subscriptions.check_in.submit')}</Button>
            </form>
          )}
          {outcome && (
            <Card className={cn('border-2', outcome.allowed ? 'border-emerald-500 bg-emerald-50' : 'border-red-500 bg-red-50')}>
              <CardContent className="flex items-center gap-4 py-6">
                {outcome.allowed ? <CircleCheck className="size-14 shrink-0 text-emerald-600" /> : <CircleX className="size-14 shrink-0 text-red-600" />}
                <div className="flex flex-col gap-1">
                  <p className="text-xl font-semibold">{t(`subscriptions.check_in_result.${outcome.result}`)}</p>
                  {outcome.member && <Link href={url(`/subscriptions/members/${outcome.member.id}`)} className="underline">{outcome.allowed ? t('subscriptions.check_in.welcome', { name: outcome.member.name }) : outcome.member.name} · <span dir="ltr">{outcome.member.number}</span></Link>}
                  {outcome.membership && <p className="text-sm text-muted-foreground">{t('subscriptions.check_in.until', { date: outcome.membership.ends_on })}{outcome.membership.sessions_left !== null ? ` · ${t('subscriptions.members.sessions_left', { count: outcome.membership.sessions_left })}` : ''}</p>}
                </div>
              </CardContent>
            </Card>
          )}
        </div>
        <div>
          <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{t('subscriptions.check_in.recent')}</h2>
          <DataTable<Recent>
            rows={recent}
            rowHref={(row) => url(`/subscriptions/members/${row.member_id}`)}
            empty={t('subscriptions.check_in.none')}
            columns={[
              { key: 'time', label: t('subscriptions.members.fields.time'), render: (row) => <span dir="ltr" className="tabular-nums">{row.time}</span> },
              { key: 'member', label: t('subscriptions.members.fields.name'), render: (row) => <span>{row.member} <span dir="ltr" className="text-xs text-muted-foreground">{row.number}</span></span> },
              { key: 'result', label: t('subscriptions.members.fields.result'), render: (row) => <span className={row.allowed ? 'text-emerald-700' : 'text-destructive'}>{t(`subscriptions.check_in_result.${row.reason ?? 'allowed'}`)}</span> },
            ]}
          />
        </div>
      </div>
    </SuiteLayout>
  );
}
