import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import FormField from '@/components/platform/FormField';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useFilters, type Filters } from '@/lib/useFilters';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface TrialRow { account_id: number; code: string; name: string; type: string; debit: string; credit: string; balance: string }

interface Props {
  trial_balance: TrialRow[];
  profit_and_loss: { revenue: { name: string; amount: string }[]; expenses: { name: string; amount: string }[]; total_revenue: string; total_expenses: string; profit: string };
  vat: { sales: string; output_vat: string; purchases: string; input_vat: string; net_due: string };
  filters: Filters;
  companies: Option[];
  branches: (Option & { company_id: number })[];
}

/** Trial balance, profit and loss, and the VAT return, straight from the ledger. */
export default function Reports({ trial_balance: trial, profit_and_loss: pl, vat, filters: initialFilters, companies, branches }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);
  const r = (key: string) => t(`finance.reports.${key}`);
  const money = (value: string) => formatMoney(value, locale);
  const row = (label: string, value: string, strong = false) => (
    <div className={cn('flex items-center justify-between py-1.5 text-sm', strong && 'font-semibold')}><span>{label}</span><span dir="ltr">{money(value)}</span></div>
  );

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('lede')} />
      <div className="mb-4 flex flex-wrap items-end gap-2">
        <FormField label={r('from')}><Input type="date" dir="ltr" value={String(filters.from ?? '')} onChange={(event) => setFilter('from', event.target.value)} /></FormField>
        <FormField label={r('to')}><Input type="date" dir="ltr" value={String(filters.to ?? '')} onChange={(event) => setFilter('to', event.target.value)} /></FormField>
        <FormField label={r('company')}><SelectInput className="w-48" value={String(filters.company_id ?? '')} onChange={(value) => setFilter('company_id', value)} options={companies} emptyLabel={r('all')} /></FormField>
        <FormField label={r('branch')}><SelectInput className="w-56" value={String(filters.branch_id ?? '')} onChange={(value) => setFilter('branch_id', value)} options={branches.filter((branch) => !filters.company_id || String(branch.company_id) === String(filters.company_id))} emptyLabel={r('all')} /></FormField>
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{r('profit_and_loss')}</CardTitle></CardHeader>
          <CardContent className="divide-y">
            <div className="pb-2">
              <div className="text-xs font-medium uppercase text-muted-foreground">{r('revenue')}</div>
              {pl.revenue.map((item) => <div key={item.name}>{row(item.name, item.amount)}</div>)}
              {row(r('revenue'), pl.total_revenue, true)}
            </div>
            <div className="py-2">
              <div className="text-xs font-medium uppercase text-muted-foreground">{r('expenses')}</div>
              {pl.expenses.map((item) => <div key={item.name}>{row(item.name, item.amount)}</div>)}
              {row(r('expenses'), pl.total_expenses, true)}
            </div>
            <div className={cn('pt-2', Number(pl.profit) < 0 && 'text-destructive')}>{row(r('profit'), pl.profit, true)}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{r('vat')}</CardTitle>
            <p className="text-xs text-muted-foreground">{r('vat_hint')}</p>
          </CardHeader>
          <CardContent className="divide-y">
            {row(r('sales'), vat.sales)}
            {row(r('output_vat'), vat.output_vat)}
            {row(r('purchases'), vat.purchases)}
            {row(r('input_vat'), vat.input_vat)}
            {row(r('net_due'), vat.net_due, true)}
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle className="text-base">{r('trial_balance')}</CardTitle></CardHeader>
          <CardContent>
            {trial.length === 0 ? <p className="text-sm text-muted-foreground">{r('empty')}</p> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead><tr className="border-b text-muted-foreground"><th className="py-1.5 text-start">{t('finance.ledger.account')}</th><th className="text-end">{t('finance.ledger.debit')}</th><th className="text-end">{t('finance.ledger.credit')}</th><th className="text-end">{r('balance')}</th></tr></thead>
                  <tbody>
                    {trial.map((item) => (
                      <tr key={item.account_id} className="border-b last:border-0">
                        <td className="py-1.5"><span className="font-mono text-xs text-muted-foreground">{item.code}</span> {item.name}</td>
                        <td className="text-end" dir="ltr">{money(item.debit)}</td>
                        <td className="text-end" dir="ltr">{money(item.credit)}</td>
                        <td className="text-end font-medium" dir="ltr">{money(item.balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </SuiteLayout>
  );
}
