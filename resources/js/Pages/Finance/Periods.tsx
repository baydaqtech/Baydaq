import { useMemo } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DatePicker from '@/components/platform/DatePicker';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface AccountRow { id: number; code: string; name: string; type: 'asset' | 'liability' | 'equity' }
type Balances = Record<number, { debit: string; credit: string }>;

interface Props {
  companies: Option[];
  company_id: number | null;
  accounts: AccountRow[];
  opening: { date: string; balances: Balances } | null;
  closes: { year: number; profit: string; closed_at: string }[];
  closable: number[];
}

/** Opening balances when a company moved to Baydaq, and closing its finished years. */
export default function Periods({ companies, company_id: companyId, accounts, opening, closes, closable }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const p = (key: string, replace?: Record<string, string>) => t(`finance.periods.${key}`, replace);
  const form = useForm<{ company_id: number | null; date: string; balances: Balances }>({ company_id: companyId, date: opening?.date ?? '', balances: opening?.balances ?? {} });

  const difference = useMemo(() => Object.values(form.data.balances).reduce((sum, line) => sum + Number(line.debit || 0) - Number(line.credit || 0), 0), [form.data.balances]);
  const set = (accountId: number, side: 'debit' | 'credit', value: string) => form.setData('balances', {
    ...form.data.balances,
    [accountId]: { ...(form.data.balances[accountId] ?? { debit: '', credit: '' }), [side]: value },
  });
  const errors = form.errors as Record<string, string>;

  return (
    <SuiteLayout title={p('title')}>
      <PageHeader title={p('title')} description={p('lede')} />
      {companies.length === 0 ? <StatusMessage>{p('no_company')}</StatusMessage> : (
        <div className="flex flex-col gap-4">
          {companies.length > 1 && (
            <FormField label={p('company')} className="max-w-xs">
              <SelectInput value={companyId} onChange={(value) => router.get(url('/finance/periods'), { company: value }, { preserveScroll: true })} options={companies} />
            </FormField>
          )}

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{p('opening')}</CardTitle>
              <CardDescription>{p('opening_lede')}</CardDescription>
            </CardHeader>
            <CardContent>
              <form className="flex flex-col gap-4" onSubmit={(event) => { event.preventDefault(); form.put(url('/finance/opening'), { preserveScroll: true }); }}>
                <FormField label={p('date')} error={errors.date} className="max-w-xs">
                  <DatePicker value={form.data.date} onChange={(value) => form.setData('date', value)} />
                </FormField>
                <div className="overflow-x-auto rounded-lg border">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-xs text-muted-foreground">
                      <tr><th className="px-3 py-2 text-start font-medium" /><th className="w-36 px-3 py-2 text-start font-medium">{p('debit')}</th><th className="w-36 px-3 py-2 text-start font-medium">{p('credit')}</th></tr>
                    </thead>
                    <tbody className="divide-y">
                      {accounts.map((account) => (
                        <tr key={account.id}>
                          <td className="px-3 py-1.5"><span className="tabular-nums text-muted-foreground">{account.code}</span> {account.name}</td>
                          {(['debit', 'credit'] as const).map((side) => (
                            <td key={side} className="px-3 py-1.5">
                              <Input type="number" step="0.01" min="0" dir="ltr" className="h-7 tabular-nums" aria-label={`${account.name} · ${p(side)}`} value={form.data.balances[account.id]?.[side] ?? ''} onChange={(event) => set(account.id, side, event.target.value)} />
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {errors.balances && <p className="text-sm text-destructive">{errors.balances}</p>}
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-sm text-muted-foreground">{p('difference', { amount: formatMoney(Math.abs(difference).toFixed(2), locale) })}</span>
                  <Button type="submit" disabled={form.processing || form.data.date === ''}>{p('save')}</Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{p('close')}</CardTitle>
              <CardDescription>{p('close_lede')}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              {closable.length === 0 ? <p className="text-sm text-muted-foreground">{p('nothing_to_close')}</p> : (
                <div className="flex flex-wrap gap-2">
                  {closable.map((year) => (
                    <ConfirmAction key={year} href={url('/finance/closes')} data={{ company_id: companyId, year }} variant="outline" title={p('close_title', { year: String(year) })} description={p('close_hint')} confirmLabel={p('close_year', { year: String(year) })}>
                      <Lock data-icon="inline-start" />{p('close_year', { year: String(year) })}
                    </ConfirmAction>
                  ))}
                </div>
              )}
              {closes.length > 0 && (
                <div>
                  <p className="mb-2 text-sm font-medium">{p('closed_years')}</p>
                  <ul className="divide-y rounded-lg border text-sm">
                    {closes.map((close) => (
                      <li key={close.year} className="flex items-center justify-between gap-2 px-3 py-2">
                        <span className="font-medium tabular-nums">{close.year}</span>
                        <span className="flex items-center gap-2">
                          <Badge variant="outline">{p(Number(close.profit) < 0 ? 'loss' : 'profit')}</Badge>
                          <span className="tabular-nums">{formatMoney(close.profit, locale)}</span>
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </SuiteLayout>
  );
}
