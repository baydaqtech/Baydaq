import { useForm } from '@inertiajs/react';
import { Download, ShieldCheck, Star, Wallet } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import DatePicker from '@/components/platform/DatePicker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface Customer { id: number; name: string; phone: string; email: string | null; notes: string | null; wallet_balance: string; created_at: string; birth_date: string | null; gender: string | null; erased: boolean }
interface Registration { id: number; scope_type: string; source: string; branch: string | null; marketing_consent: boolean; created_at: string }
interface Entry { id: number; amount: string; balance_after: string; kind: string; source: string; note: string | null; created_at: string }
interface Activity { module: string; label: string; detail: string | null; date: string; amount: string | null; path: string | null }

interface Props {
  customer: Customer;
  entries: Entry[];
  activity: Activity[];
  registrations: Registration[];
  privacy: boolean;
  can_erase: boolean;
  companies: Option[];
  points: PointsData | null;
  can_top_up: boolean;
  can_update: boolean;
}

interface Option { value: number; label: string }

interface PointsData {
  balance: number;
  lifetime: number;
  companies: (Option & { tier: string | null; point_value: string | null })[];
  entries: { id: number; points: number; kind: string; balance_after: number; created_at: string }[];
}

/** Loyalty points: the balance and tier at each company, redeeming as wallet credit, and the latest movements. */
function PointsCard({ customer, points, canRedeem }: { customer: Customer; points: PointsData; canRedeem: boolean }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const c = (field: string, replace?: Record<string, string>) => t(`suite.customers.${field}`, replace);
  const redeemable = points.companies.filter((company) => company.point_value !== null && Number(company.point_value) > 0);
  const form = useForm({ points: '', company_id: redeemable[0]?.value ?? '' });
  const value = Number(redeemable.find((company) => company.value === form.data.company_id)?.point_value ?? 0) * Number(form.data.points || 0);

  return (
    <Card>
      <CardHeader><CardTitle className="flex items-center gap-2"><Star className="size-4" />{c('points')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <div className="flex items-baseline gap-2">
          <span className="text-3xl font-semibold tabular-nums">{formatNumber(points.balance, locale)}</span>
          <span className="text-muted-foreground">{c('points_lifetime', { points: formatNumber(points.lifetime, locale) })}</span>
        </div>
        {points.companies.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {points.companies.map((company) => <Badge key={company.value} variant="outline">{company.label}{company.tier ? ` · ${company.tier}` : ''}</Badge>)}
          </div>
        )}
        {canRedeem && redeemable.length > 0 && points.balance > 0 && (
          <form className="flex flex-col gap-2 rounded-lg border p-3" onSubmit={(event) => { event.preventDefault(); form.post(url(`/customers/${customer.id}/points`), { preserveScroll: true, onSuccess: () => form.reset('points') }); }}>
            <div className="text-sm font-medium">{c('points_redeem')}</div>
            <div className="grid gap-2 sm:grid-cols-[8rem_1fr_auto]">
              <FormField label={c('points_count')} error={form.errors.points}>
                <Input type="number" min="1" max={points.balance} dir="ltr" value={form.data.points} onChange={(event) => form.setData('points', event.target.value)} />
              </FormField>
              {redeemable.length > 1 ? (
                <FormField label={c('company')} error={form.errors.company_id}>
                  <SelectInput value={form.data.company_id} onChange={(next) => form.setData('company_id', Number(next))} options={redeemable} />
                </FormField>
              ) : <span />}
              <Button type="submit" className="self-end" disabled={form.processing || !form.data.points}>{c('points_redeem_for', { amount: formatMoney(value.toFixed(2), locale) })}</Button>
            </div>
          </form>
        )}
        {points.entries.length > 0 && (
          <ul className="divide-y">
            {points.entries.map((entry) => (
              <li key={entry.id} className="flex items-center justify-between gap-2 py-1.5">
                <span>{c(`points_kinds.${entry.kind}`)} <span className="text-muted-foreground">· {formatDate(entry.created_at, locale)}</span></span>
                <span className={cn('tabular-nums', entry.points < 0 && 'text-rose-700 dark:text-rose-400')}>{entry.points > 0 ? '+' : ''}{formatNumber(entry.points, locale)}</span>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

const PAID_WITH = ['cash', 'card', 'transfer'] as const;

/** Money into (or a correction of) the wallet, for the books of one company. */
function WalletForm({ customer, kind, companies }: { customer: Customer; kind: 'top_up' | 'adjustment'; companies: Option[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const c = (field: string) => t(`suite.customers.${field}`);
  const form = useForm({ kind, amount: '', note: '', company_id: companies[0]?.value ?? '', paid_with: kind === 'top_up' ? 'cash' : '' });

  return (
    <form
      className="flex flex-col gap-2 rounded-lg border p-3"
      onSubmit={(event) => { event.preventDefault(); form.post(url(`/customers/${customer.id}/wallet`), { preserveScroll: true, onSuccess: () => form.reset('amount', 'note') }); }}
    >
      <div className="text-sm font-medium">{kind === 'top_up' ? c('top_up') : c('adjust')}</div>
      {(companies.length > 1 || kind === 'top_up') && (
        <div className="grid gap-2 sm:grid-cols-2">
          {companies.length > 1 && (
            <FormField label={c('company')} error={form.errors.company_id}>
              <SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', Number(value))} options={companies} />
            </FormField>
          )}
          {kind === 'top_up' && (
            <FormField label={c('paid_with')} error={form.errors.paid_with}>
              <div className="flex gap-1" role="radiogroup">
                {PAID_WITH.map((method) => (
                  <Button key={method} type="button" size="sm" role="radio" aria-checked={form.data.paid_with === method} variant={form.data.paid_with === method ? 'secondary' : 'ghost'} onClick={() => form.setData('paid_with', method)}>
                    {c(`paid_with_${method}`)}
                  </Button>
                ))}
              </div>
            </FormField>
          )}
        </div>
      )}
      <div className="grid gap-2 sm:grid-cols-[8rem_1fr_auto]">
        <FormField label={c('amount')} error={form.errors.amount ?? (form.errors as Record<string, string>).wallet} hint={kind === 'adjustment' ? c('adjust_hint') : undefined}>
          <Input type="number" step="0.01" dir="ltr" min={kind === 'top_up' ? '0.01' : undefined} value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
        </FormField>
        <FormField label={c('note')} error={form.errors.note}>
          <Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />
        </FormField>
        <Button type="submit" className="self-end" variant={kind === 'top_up' ? 'default' : 'outline'} disabled={form.processing || form.data.amount === ''}>{c('save')}</Button>
      </div>
    </form>
  );
}

function PrivacyCard({ customer, canErase }: { customer: Customer; canErase: boolean }) {
  const t = useT();
  const url = useSuiteUrl();
  const p = (field: string) => t(`suite.privacy.${field}`);
  const form = useForm({ confirm: false });

  return (
    <Card>
      <CardHeader><CardTitle className="flex items-center gap-2"><ShieldCheck className="size-4" />{p('title')}{customer.erased && <Badge variant="destructive">{p('erased_badge')}</Badge>}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <Button variant="outline" className="self-start" asChild><a href={url(`/customers/${customer.id}/export`)}><Download />{p('export')}</a></Button>
        {canErase && !customer.erased && (
          <form className="flex flex-col gap-2 rounded-lg border border-destructive/40 p-3" onSubmit={(event) => { event.preventDefault(); form.post(url(`/customers/${customer.id}/erase`), { preserveScroll: true }); }}>
            <p className="text-muted-foreground">{p('erase_hint')}</p>
            <label className="flex items-center gap-2"><Checkbox checked={form.data.confirm} onCheckedChange={(checked) => form.setData('confirm', checked === true)} />{p('erase_confirm')}</label>
            <Button type="submit" variant="destructive" className="self-start" disabled={!form.data.confirm || form.processing}>{p('erase')}</Button>
          </form>
        )}
      </CardContent>
    </Card>
  );
}

export default function CustomerShow({ customer, entries, activity, registrations, privacy, can_erase: canErase, companies, points, can_top_up: canTopUp, can_update: canUpdate }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const c = (field: string) => t(`suite.customers.${field}`);
  const form = useForm({ name: customer.name, email: customer.email ?? '', notes: customer.notes ?? '', birth_date: customer.birth_date ?? '', gender: customer.gender ?? '' });

  return (
    <SuiteLayout title={customer.name}>
      <PageHeader title={customer.name} description={<span dir="ltr">+{customer.phone}</span>} />
      <div className="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
        <div className="flex flex-col gap-4">
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><Wallet className="size-4" />{c('wallet')}</CardTitle></CardHeader>
            <CardContent className="flex flex-col gap-3">
              <div className="text-3xl font-semibold">{formatMoney(customer.wallet_balance, locale)}</div>
              {canTopUp && <WalletForm customer={customer} kind="top_up" companies={companies} />}
              {canUpdate && <WalletForm customer={customer} kind="adjustment" companies={companies} />}
            </CardContent>
          </Card>
          {points && <PointsCard customer={customer} points={points} canRedeem={canTopUp || canUpdate} />}
          <Card>
            <CardContent>
              <form className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.put(url(`/customers/${customer.id}`), { preserveScroll: true }); }}>
                <FormField label={c('name')} error={form.errors.name}><Input value={form.data.name} disabled={!canUpdate} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
                <FormField label={c('email')} error={form.errors.email}><Input type="email" dir="ltr" value={form.data.email} disabled={!canUpdate} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
                <div className="grid gap-3 sm:grid-cols-2">
                  <FormField label={c('birth_date')} error={form.errors.birth_date}><DatePicker value={form.data.birth_date} disabled={!canUpdate} onChange={(value) => form.setData('birth_date', value)} /></FormField>
                  <FormField label={c('gender')} error={form.errors.gender}>
                    <SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} emptyLabel="—" options={['male', 'female'].map((value) => ({ value, label: c(`genders.${value}`) }))} />
                  </FormField>
                </div>
                <FormField label={c('notes')} error={form.errors.notes}><Textarea rows={3} value={form.data.notes} disabled={!canUpdate} onChange={(event) => form.setData('notes', event.target.value)} /></FormField>
                <p className="text-xs text-muted-foreground">{c('since')}: {formatDate(customer.created_at, locale)}</p>
                {canUpdate && <Button type="submit" className="self-start" disabled={form.processing || !form.isDirty}>{c('save')}</Button>}
              </form>
            </CardContent>
          </Card>
        </div>
        <div className="flex flex-col gap-4">
          {privacy && <PrivacyCard customer={customer} canErase={canErase} />}
          <Card>
            <CardHeader><CardTitle>{c('registrations')}</CardTitle></CardHeader>
            <CardContent>
              {registrations.length === 0 && <p className="text-sm text-muted-foreground">{c('no_registrations')}</p>}
              <ul className="divide-y">
                {registrations.map((registration) => (
                  <li key={registration.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 font-medium"><Badge variant="outline">{c(`scopes.${registration.scope_type}`)}</Badge><span className="truncate">{registration.branch}</span></div>
                      <div className="text-xs text-muted-foreground">{formatDate(registration.created_at, locale)} · {c(`sources.${registration.source}`)}</div>
                    </div>
                    {registration.marketing_consent && <Badge variant="secondary">{c('consented')}</Badge>}
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{c('activity')}</CardTitle></CardHeader>
            <CardContent>
              {activity.length === 0 && <p className="text-sm text-muted-foreground">{c('no_activity')}</p>}
              <ul className="divide-y">
                {activity.map((item, index) => {
                  const body = (
                    <div className="flex items-center justify-between gap-3 py-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2 text-sm font-medium"><Badge variant="outline">{c(`modules.${item.module}`)}</Badge><span className="truncate">{item.label}</span></div>
                        <div className="text-xs text-muted-foreground">{formatDate(item.date, locale)}{item.detail && ` · ${item.detail}`}</div>
                      </div>
                      {item.amount !== null && <span className="shrink-0 text-sm">{formatMoney(item.amount, locale)}</span>}
                    </div>
                  );

                  return <li key={`${item.module}-${item.date}-${index}`}>{item.path ? <Link href={url(item.path)} className="block hover:bg-muted/40">{body}</Link> : body}</li>;
                })}
              </ul>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{c('history')}</CardTitle></CardHeader>
            <CardContent>
              {entries.length === 0 && <p className="text-sm text-muted-foreground">{c('no_entries')}</p>}
              <ul className="divide-y">
                {entries.map((entry) => (
                  <li key={entry.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <div className="min-w-0">
                      <div className="font-medium">{c(`kinds.${entry.kind}`)}</div>
                      <div className="text-xs text-muted-foreground">{formatDate(entry.created_at, locale)}{entry.note && ` · ${entry.note}`}</div>
                    </div>
                    <div className="text-end">
                      <div className={cn('font-medium', Number(entry.amount) < 0 ? 'text-red-700' : 'text-emerald-700')} dir="ltr">{Number(entry.amount) > 0 ? '+' : ''}{entry.amount}</div>
                      <div className="text-xs text-muted-foreground">{formatMoney(entry.balance_after, locale)}</div>
                    </div>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </div>
      </div>
    </SuiteLayout>
  );
}
