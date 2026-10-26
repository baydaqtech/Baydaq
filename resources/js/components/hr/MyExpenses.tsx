import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface ExpensesData {
  claims: { id: number; spent_on: string; category: string; amount: string; merchant: string | null; status: string; decision_note: string | null }[];
  categories: string[];
}

/** My expense claims and a form to send a new one with its receipt. */
export default function MyExpenses({ data }: { data: ExpensesData }) {
  const t = useT();
  const { locale } = useLocale();
  const e = (key: string) => t(`hr.expenses.${key}`);
  const [claiming, setClaiming] = useState(false);
  const form = useForm<{ spent_on: string; category: string; amount: string; vat: string; merchant: string; merchant_vat_number: string; description: string; receipt: File | null }>({
    spent_on: new Date().toISOString().slice(0, 10), category: 'fuel', amount: '', vat: '', merchant: '', merchant_vat_number: '', description: '', receipt: null,
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2"><CardTitle className="text-base">{e('mine')}</CardTitle>{!claiming && <Button size="sm" variant="outline" onClick={() => setClaiming(true)}>{e('claim')}</Button>}</CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        {claiming && (
          <form className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post('/me/expenses', { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); setClaiming(false); } }); }}>
            <FormField label={e('spent_on')} error={form.errors.spent_on}><Input type="date" dir="ltr" value={form.data.spent_on} onChange={(event) => form.setData('spent_on', event.target.value)} /></FormField>
            <FormField label={e('category')} error={form.errors.category}><SelectInput value={form.data.category} onChange={(value) => form.setData('category', value)} options={data.categories.map((value) => ({ value, label: e(`categories.${value}`) }))} /></FormField>
            <FormField label={e('amount')} error={form.errors.amount}><Input type="number" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
            <FormField label={e('vat')} error={form.errors.vat}><Input type="number" step="0.01" dir="ltr" value={form.data.vat} onChange={(event) => form.setData('vat', event.target.value)} /></FormField>
            <FormField label={e('merchant')} error={form.errors.merchant}><Input value={form.data.merchant} onChange={(event) => form.setData('merchant', event.target.value)} /></FormField>
            <FormField label={e('merchant_vat_number')} error={form.errors.merchant_vat_number}><Input dir="ltr" value={form.data.merchant_vat_number} onChange={(event) => form.setData('merchant_vat_number', event.target.value)} /></FormField>
            <FormField label={e('description')} error={form.errors.description}><Input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
            <FormField label={e('receipt')} error={form.errors.receipt}><Input type="file" accept="image/*,.pdf" capture="environment" onChange={(event) => form.setData('receipt', event.target.files?.[0] ?? null)} /></FormField>
            <div className="flex gap-2 sm:col-span-2">
              <Button type="submit" disabled={form.processing || form.data.receipt === null}>{e('claim')}</Button>
              <Button type="button" variant="ghost" onClick={() => setClaiming(false)}>{t('hr.separations.cancel')}</Button>
            </div>
          </form>
        )}
        {data.claims.length > 0 && (
          <ul className="divide-y">
            {data.claims.map((claim) => (
              <li key={claim.id} className="flex items-center justify-between py-1.5">
                <span>{formatDate(claim.spent_on, locale)} · {e(`categories.${claim.category}`)}{claim.merchant && ` · ${claim.merchant}`}</span>
                <span className="flex items-center gap-2"><span dir="ltr">{formatMoney(claim.amount, locale)}</span><Badge variant="outline">{e(`statuses.${claim.status}`)}</Badge></span>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
