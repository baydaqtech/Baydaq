import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Ban, Copy, Pencil, Plus, RefreshCw, Snowflake } from 'lucide-react';
import { toast } from 'sonner';
import { MemberFields } from '@/Pages/Subscriptions/Members/Index';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import StatusMessage from '@/components/platform/StatusMessage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface MemberInfo { id: number; number: string; name: string; phone: string; email: string | null; gender: string | null; birth_date: string | null; note: string | null; is_active: boolean; card_url: string }
interface MembershipRow {
  id: number; plan: string; status: string; starts_on: string; ends_on: string; is_current: boolean; is_frozen: boolean; sessions_total: number | null; sessions_left: number | null;
  price: string; freeze_days_left: number; invoice_id: number | null; family_size: number; bnpl_provider: string | null; is_corporate: boolean; dependents: { id: number; name: string; number: string }[]; freezes: { starts_on: string; ends_on: string; days: number; reason: string | null }[];
}
interface CheckInRow { id: number; at: string; branch: string | null; allowed: boolean; reason: string | null; used_session: boolean; [key: string]: unknown }
interface PlanOption extends Option { price: string; gender: string }

interface Props { member: MemberInfo; memberships: MembershipRow[]; check_ins: CheckInRow[]; plans: PlanOption[]; payment_means: Option[]; today: string }

function SellDialog({ memberId, plans, paymentMeans, renew }: { memberId: number; plans: PlanOption[]; paymentMeans: Option[]; renew?: MembershipRow }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`subscriptions.members.fields.${field}`);
  const form = useForm({ plan_id: plans[0]?.value ?? '', starts_on: '', payment_means: '10', renew_id: renew?.id ?? null, bnpl_provider: '' });
  const plan = plans.find((item) => item.value === Number(form.data.plan_id));

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, starts_on: data.starts_on || null }));
    form.post(url(`/subscriptions/members/${memberId}/memberships`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{renew ? <Button size="sm" variant="outline"><RefreshCw data-icon="inline-start" />{t('subscriptions.members.renew')}</Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('subscriptions.members.sell')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{renew ? t('subscriptions.members.renew') : t('subscriptions.members.sell')}</DialogTitle>{renew && <DialogDescription>{renew.plan} · <span dir="ltr">{renew.ends_on}</span></DialogDescription>}</DialogHeader>
          <FormField label={f('plan')} error={form.errors.plan_id}><SelectInput value={form.data.plan_id} onChange={(value) => form.setData('plan_id', Number(value))} options={plans} /></FormField>
          {!renew && <FormField label={f('starts_on')} error={form.errors.starts_on}><Input dir="ltr" type="date" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>}
          {renew && form.errors.starts_on && <p className="text-sm text-destructive">{form.errors.starts_on}</p>}
          <FormField label={f('payment_means')}><SelectInput value={form.data.payment_means} onChange={(value) => form.setData('payment_means', value)} options={paymentMeans} /></FormField>
          <FormField label={f('bnpl_provider')} hint={t('subscriptions.members.bnpl_hint')}><SelectInput value={form.data.bnpl_provider} onChange={(value) => form.setData('bnpl_provider', value)} options={[{ value: 'tamara', label: 'Tamara' }, { value: 'tabby', label: 'Tabby' }]} emptyLabel="—" /></FormField>
          {plan && <p className="text-2xl font-semibold tabular-nums">{formatMoney(plan.price, locale)}</p>}
          <DialogFooter><Button type="submit" disabled={form.processing || !plan}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** Family members sharing this membership (S2). */
function FamilyMembers({ membership }: { membership: MembershipRow }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const form = useForm({ member: '' });
  const full = membership.dependents.length + 1 >= membership.family_size;

  return (
    <div className="mt-1 rounded-md border p-2 text-xs">
      <div className="mb-1 font-medium">{t('subscriptions.members.family', { count: String(membership.dependents.length + 1), size: String(membership.family_size) })}</div>
      <ul className="flex flex-col gap-1">
        {membership.dependents.map((dependent) => (
          <li key={dependent.id} className="flex items-center justify-between gap-2">
            <Link href={url(`/subscriptions/members/${dependent.id}`)} className="hover:underline">{dependent.name} <span dir="ltr" className="text-muted-foreground">{dependent.number}</span></Link>
            {can('subscriptions.members.sell') && <ConfirmAction href={url(`/subscriptions/memberships/${membership.id}/dependents/${dependent.id}`)} method="delete" size="xs" variant="ghost" title={t('common.actions.remove')}>{t('common.actions.remove')}</ConfirmAction>}
          </li>
        ))}
      </ul>
      {can('subscriptions.members.sell') && membership.status === 'active' && !full && (
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/subscriptions/memberships/${membership.id}/dependents`), { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mt-2 flex gap-1">
          <Input className="h-8" dir="ltr" placeholder={t('subscriptions.members.family_add_placeholder')} value={form.data.member} onChange={(event) => form.setData('member', event.target.value)} />
          <Button type="submit" size="sm" disabled={form.processing || !form.data.member}>{t('common.actions.add_row')}</Button>
        </form>
      )}
      {form.errors.member && <p className="mt-1 text-destructive">{form.errors.member}</p>}
    </div>
  );
}

function FreezeDialog({ membership, today }: { membership: MembershipRow; today: string }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`subscriptions.members.fields.${field}`);
  const form = useForm({ starts_on: today, ends_on: today, reason: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline" disabled={membership.freeze_days_left === 0}><Snowflake data-icon="inline-start" />{t('subscriptions.members.freeze')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/subscriptions/memberships/${membership.id}/freeze`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('subscriptions.members.freeze')}</DialogTitle><DialogDescription>{t('subscriptions.members.freeze_left', { count: membership.freeze_days_left })}</DialogDescription></DialogHeader>
          <FormField label={f('from')} error={form.errors.starts_on}><Input dir="ltr" type="date" min={today} value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
          <FormField label={f('to')} error={form.errors.ends_on}><Input dir="ltr" type="date" min={form.data.starts_on} value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} /></FormField>
          <FormField className="sm:col-span-2" label={f('reason')}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('subscriptions.members.freeze')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CancelDialog({ membership }: { membership: MembershipRow }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ reason: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="ghost"><Ban data-icon="inline-start" />{t('subscriptions.members.cancel')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/subscriptions/memberships/${membership.id}/cancel`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('subscriptions.members.cancel')}</DialogTitle><DialogDescription>{membership.plan}</DialogDescription></DialogHeader>
          <FormField label={t('subscriptions.members.fields.reason')}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" variant="destructive" disabled={form.processing}>{t('subscriptions.members.cancel')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function EditMember({ member }: { member: MemberInfo }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ name: member.name, phone: member.phone, email: member.email ?? '', gender: member.gender ?? '', birth_date: member.birth_date ?? '', note: member.note ?? '', is_active: member.is_active });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, gender: data.gender || null, birth_date: data.birth_date || null, email: data.email || null }));
    form.put(url(`/subscriptions/members/${member.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button></DialogTrigger>
      <DialogContent dir={dir} className="max-w-xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{member.name}</DialogTitle></DialogHeader>
          <MemberFields form={form as never} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function MemberShow({ member, memberships, check_ins: checkIns, plans, payment_means: paymentMeans, today }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const f = (field: string) => t(`subscriptions.members.fields.${field}`);
  const latestActive = memberships.find((membership) => membership.status === 'active');

  return (
    <SuiteLayout title={member.name}>
      <PageHeader
        title={<span className="flex items-center gap-2">{member.name} <span dir="ltr" className="font-mono text-sm text-muted-foreground">{member.number}</span>{can('subscriptions.members.update') && <EditMember member={member} />}</span>}
        description={<span dir="ltr">{member.phone}</span>}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="ghost" onClick={() => navigator.clipboard?.writeText(member.card_url).then(() => toast.success(t('subscriptions.members.copied')))}><Copy data-icon="inline-start" />{t('subscriptions.members.copy_card')}</Button>
            {can('subscriptions.members.update') && <ConfirmAction href={url(`/subscriptions/members/${member.id}/card`)} size="sm" variant="ghost" title={t('subscriptions.members.replace_card')} description={t('subscriptions.members.replace_card_hint')}>{t('subscriptions.members.replace_card')}</ConfirmAction>}
            {can('subscriptions.members.sell') && plans.length > 0 && <SellDialog memberId={member.id} plans={plans} paymentMeans={paymentMeans} />}
          </div>
        }
      />

      <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{t('subscriptions.members.memberships')}</h2>
      {memberships.length === 0 ? <StatusMessage className="mb-6">{t('subscriptions.members.no_membership')}</StatusMessage> : (
        <div className="mb-6 grid gap-3 md:grid-cols-2">
          {memberships.map((membership) => (
            <Card key={membership.id} className={membership.is_current ? 'border-brass' : undefined}>
              <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                  {membership.plan}
                  <StatusBadge file="subscriptions" group="membership_status" value={membership.status} />
                  {membership.is_current && <Badge variant="outline" className="border-transparent bg-emerald-100 text-emerald-800">{t('subscriptions.members.current')}</Badge>}
                  {membership.is_frozen && <Badge variant="outline" className="border-transparent bg-sky-100 text-sky-800">{t('subscriptions.members.frozen_now')}</Badge>}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-1 text-sm">
                <span dir="ltr" className="tabular-nums">{membership.starts_on} → {membership.ends_on}</span>
                {membership.sessions_left !== null && <span>{t('subscriptions.members.sessions_left', { count: membership.sessions_left })}</span>}
                <span className="text-muted-foreground">{formatMoney(membership.price, locale)}</span>
                {membership.bnpl_provider && <span className="text-xs text-muted-foreground">{t('subscriptions.members.paid_with', { provider: membership.bnpl_provider === 'tamara' ? 'Tamara' : 'Tabby' })}</span>}
                {membership.family_size > 1 && <FamilyMembers membership={membership} />}
                {membership.freezes.map((freeze, index) => <span key={index} className="text-xs text-sky-700"><Snowflake className="me-1 inline size-3" /><span dir="ltr">{freeze.starts_on} → {freeze.ends_on}</span> ({freeze.days}){freeze.reason ? ` · ${freeze.reason}` : ''}</span>)}
              </CardContent>
              {membership.status === 'active' && (
                <CardFooter className="flex flex-wrap gap-2">
                  {membership.id === latestActive?.id && can('subscriptions.members.sell') && <SellDialog memberId={member.id} plans={plans} paymentMeans={paymentMeans} renew={membership} />}
                  {can('subscriptions.members.freeze') && membership.ends_on >= today && <FreezeDialog membership={membership} today={today} />}
                  {can('subscriptions.members.cancel') && <CancelDialog membership={membership} />}
                </CardFooter>
              )}
            </Card>
          ))}
        </div>
      )}

      <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{t('subscriptions.members.check_ins')}</h2>
      <DataTable<CheckInRow>
        rows={checkIns}
        empty={t('subscriptions.check_in.none')}
        columns={[
          { key: 'at', label: f('time'), render: (row) => <span dir="ltr" className="tabular-nums">{row.at}</span> },
          { key: 'branch', label: f('branch'), render: (row) => row.branch },
          { key: 'result', label: f('result'), render: (row) => <span className={row.allowed ? 'text-emerald-700' : 'text-destructive'}>{t(`subscriptions.check_in_result.${row.reason ?? 'allowed'}`)}</span> },
        ]}
      />
    </SuiteLayout>
  );
}
