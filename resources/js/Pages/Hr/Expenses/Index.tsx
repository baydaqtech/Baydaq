import { Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface Claim {
  id: number; employee_id: number; employee: string; spent_on: string; category: string; amount: string; vat: string; merchant: string | null;
  description: string | null; status: string; decision_note: string | null; paid_on: string | null; has_receipt: boolean; can_decide: boolean; can_pay: boolean;
}

/** Expense claims to approve and pay back. */
export default function ExpensesIndex({ claims, totals, filters }: { claims: Claim[]; totals: { submitted: string; approved: string }; filters: { status: string } }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const e = (key: string) => t(`hr.expenses.${key}`);
  const act = (claim: Claim, path: string, data: Record<string, string | boolean> = {}) => router.put(url(`/hr/expenses/${claim.id}/${path}`), data, { preserveScroll: true });

  return (
    <SuiteLayout title={e('title')}>
      <PageHeader title={e('title')} description={e('lede')} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <Card><CardContent><div className="text-sm text-muted-foreground">{e('waiting_total')}</div><div className="text-2xl font-semibold">{formatMoney(totals.submitted, locale)}</div></CardContent></Card>
        <Card><CardContent><div className="text-sm text-muted-foreground">{e('to_pay_total')}</div><div className="text-2xl font-semibold">{formatMoney(totals.approved, locale)}</div></CardContent></Card>
      </div>
      <div className="mb-3 flex flex-wrap gap-1">
        {['submitted', 'approved', 'paid', 'rejected', 'all'].map((status) => (
          <Button key={status} size="sm" variant={filters.status === status ? 'secondary' : 'ghost'} asChild><Link href={url(`/hr/expenses?status=${status}`)}>{e(`statuses.${status}`)}</Link></Button>
        ))}
      </div>
      <Card><CardContent className="divide-y">
        {claims.length === 0 && <p className="text-sm text-muted-foreground">{e('none')}</p>}
        {claims.map((claim) => (
          <div key={claim.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <Link href={url(`/hr/employees/${claim.employee_id}`)} className="font-medium hover:underline">{claim.employee}</Link>
                <Badge variant="outline">{e(`categories.${claim.category}`)}</Badge>
                <Badge variant="secondary">{e(`statuses.${claim.status}`)}</Badge>
              </div>
              <div className="text-xs text-muted-foreground">{formatDate(claim.spent_on, locale)} · {[claim.merchant, claim.description].filter(Boolean).join(' · ')}</div>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-end"><span className="font-medium">{formatMoney(claim.amount, locale)}</span>{Number(claim.vat) > 0 && <span className="block text-xs text-muted-foreground">VAT {formatMoney(claim.vat, locale)}</span>}</span>
              {claim.has_receipt && <Button size="sm" variant="ghost" asChild><a href={url(`/hr/expenses/${claim.id}/receipt`)} target="_blank" rel="noreferrer"><FileText />{e('view_receipt')}</a></Button>}
              {claim.status === 'submitted' && claim.can_decide && <>
                <Button size="sm" onClick={() => act(claim, 'decision', { approve: true })}>{e('approve')}</Button>
                <Button size="sm" variant="ghost" onClick={() => act(claim, 'decision', { approve: false })}>{e('reject')}</Button>
              </>}
              {claim.status === 'approved' && claim.can_pay && <Button size="sm" variant="outline" onClick={() => act(claim, 'paid')}>{e('mark_paid')}</Button>}
            </div>
          </div>
        ))}
      </CardContent></Card>
    </SuiteLayout>
  );
}
