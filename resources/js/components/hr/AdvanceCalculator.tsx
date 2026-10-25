import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import FormField from '@/components/platform/FormField';
import StatusBadge from '@/components/platform/StatusBadge';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface AdvanceData {
  monthly_wage: string;
  max_amount: string;
  max_months: number;
  max_installments: number;
  open: boolean;
  first_month: string;
  items: { id: number; amount: string; installments: number; status: string; balance: string; first_installment_month: string }[];
}

/** Same split as the server: equal installments rounded down to the halala, remainder on the last. */
function firstInstallment(amount: number, count: number): number {
  const halalas = Math.round(amount * 100);

  return Math.floor(halalas / count) / 100;
}

/** Self-service salary advance: the ceiling, a live plan preview, then the request (H18). */
export default function AdvanceCalculator({ data }: { data: AdvanceData }) {
  const t = useT();
  const { locale } = useLocale();
  const [open, setOpen] = useState(false);
  const form = useForm({ amount: '', installments: '3', first_installment_month: data.first_month, reason: '' });
  const money = (value: string | number) => formatMoney(value, locale);
  const amount = Number(form.data.amount) || 0;
  const count = Math.max(1, Number(form.data.installments) || 1);
  const tooMuch = amount > Number(data.max_amount);
  const plan = useMemo(() => {
    const each = firstInstallment(amount, count);
    const wage = Number(data.monthly_wage);

    return { each, share: wage > 0 ? ((each / wage) * 100).toFixed(1) : '0' };
  }, [amount, count, data.monthly_wage]);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-base">{t('hr.me.advance')}</CardTitle>
        {!data.open && Number(data.max_amount) > 0 && <Button size="sm" variant="outline" onClick={() => setOpen(!open)}>{t('hr.me.advance_new')}</Button>}
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        {data.open ? <StatusMessage>{t('hr.me.advance_open')}</StatusMessage> : <p className="text-muted-foreground">{t('hr.me.advance_limit', { amount: money(data.max_amount), months: String(data.max_months), installments: String(data.max_installments) })}</p>}
        {open && (
          <form onSubmit={(event) => { event.preventDefault(); form.transform((payload) => ({ ...payload, first_installment_month: `${payload.first_installment_month}-01` })); form.post('/me/advance', { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } }); }} className="grid gap-3 sm:grid-cols-3">
            <FormField label={t('hr.pay.fields.amount')} error={tooMuch ? t('hr.me.advance_too_much') : form.errors.amount}><Input type="number" min="1" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
            <FormField label={t('hr.me.installments')} error={form.errors.installments}><Input type="number" min="1" max={data.max_installments} dir="ltr" value={form.data.installments} onChange={(event) => form.setData('installments', event.target.value)} /></FormField>
            <FormField label={t('hr.me.first_month')} error={form.errors.first_installment_month}><Input type="month" dir="ltr" value={form.data.first_installment_month} onChange={(event) => form.setData('first_installment_month', event.target.value)} /></FormField>
            {amount > 0 && !tooMuch && <p className="rounded-md bg-muted px-3 py-2 sm:col-span-3">{t('hr.me.advance_plan', { count: String(count), amount: money(plan.each), share: plan.share, month: form.data.first_installment_month })}</p>}
            <div className="sm:col-span-3"><Button type="submit" disabled={form.processing || tooMuch || amount <= 0}>{t('hr.me.advance_new')}</Button></div>
          </form>
        )}
        {data.items.length > 0 && (
          <ul className="divide-y">
            {data.items.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{money(item.amount)} · {item.installments} × · <span dir="ltr">{item.first_installment_month}</span></span>
                <span className="flex items-center gap-2 text-muted-foreground">{money(item.balance)} <StatusBadge group="advance_status" value={item.status} file="hr" /></span>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
