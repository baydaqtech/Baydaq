import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown, FileText, Plus } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface Payslip {
  id: number;
  month: string;
  status: 'approved' | 'paid';
  paid_on: string | null;
  gross: string;
  taken: string;
  net: string;
  breakdown: { kind: string; label_ar: string; label_en: string; amount: string }[];
}

export interface MyLetter { id: number; reference: string; kind: 'salary' | 'experience'; language: string; addressed_to: string | null; issued_at: string; valid: boolean }

function monthName(month: string, locale: string): string {
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month}-01T00:00:00Z`));
}

/** My payslips: net pay per month, the lines behind it on demand, and the PDF. */
export function MyPayslips({ payslips }: { payslips: Payslip[] }) {
  const t = useT();
  const { locale } = useLocale();
  const [open, setOpen] = useState<number | null>(null);
  const p = (key: string) => t(`hr.payslips.${key}`);

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{p('title')}</CardTitle></CardHeader>
      <CardContent className="divide-y text-sm">
        {payslips.length === 0 && <p className="text-muted-foreground">{p('none')}</p>}
        {payslips.map((payslip) => (
          <div key={payslip.id} className="py-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <button type="button" className="flex items-center gap-2 font-medium" aria-expanded={open === payslip.id} onClick={() => setOpen(open === payslip.id ? null : payslip.id)}>
                <ChevronDown className={cn('size-4 transition-transform', open === payslip.id && 'rotate-180')} />
                {monthName(payslip.month, locale)}
              </button>
              <span className="flex items-center gap-2">
                <span className="font-semibold tabular-nums">{formatMoney(payslip.net, locale)}</span>
                <StatusBadge group="payroll_status" value={payslip.status} file="hr" />
                <Button asChild size="icon-sm" variant="ghost" aria-label={p('open_pdf')}><a href={`/me/payslips/${payslip.id}/pdf`} target="_blank" rel="noreferrer"><FileText /></a></Button>
              </span>
            </div>
            {open === payslip.id && (
              <dl className="mt-2 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 rounded-lg bg-muted/50 p-3">
                {payslip.breakdown.map((line, index) => (
                  <div key={index} className="contents">
                    <dt className="text-muted-foreground">{locale === 'ar' ? line.label_ar : line.label_en}</dt>
                    <dd className={cn('text-end tabular-nums', line.amount.startsWith('-') && 'text-rose-700 dark:text-rose-400')}>{formatMoney(line.amount, locale)}</dd>
                  </div>
                ))}
                <dt className="border-t pt-1 font-medium">{p('net')}</dt>
                <dd className="border-t pt-1 text-end font-semibold tabular-nums">{formatMoney(payslip.net, locale)}</dd>
              </dl>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

/** Salary and experience letters: asked for here, ready at once, each with its QR check. */
export function MyLetters({ letters, kinds }: { letters: MyLetter[]; kinds: ('salary' | 'experience')[] }) {
  const t = useT();
  const { locale } = useLocale();
  const [asking, setAsking] = useState(false);
  const l = (key: string) => t(`hr.letters.${key}`);
  const form = useForm({ kind: kinds[0] ?? 'experience', language: locale, addressed_to: '' });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="text-base">{l('mine')}</CardTitle>
        {kinds.length > 0 && <Button size="sm" variant="outline" onClick={() => setAsking(!asking)}><Plus data-icon="inline-start" />{l('ask')}</Button>}
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        {asking && (
          <form
            className="grid gap-3 rounded-lg border p-3 sm:grid-cols-3"
            onSubmit={(event) => { event.preventDefault(); form.post('/me/letters', { preserveScroll: true, onSuccess: () => { setAsking(false); form.reset('addressed_to'); } }); }}
          >
            <FormField label={l('kind')} error={form.errors.kind}>
              <SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value as 'salary' | 'experience')} options={kinds.map((kind) => ({ value: kind, label: t(`hr.letter_kind.${kind}`) }))} />
            </FormField>
            <FormField label={l('language')} error={form.errors.language}>
              <SelectInput value={form.data.language} onChange={(value) => form.setData('language', value)} options={['ar', 'en'].map((value) => ({ value, label: t(`common.language.${value}`) }))} />
            </FormField>
            <FormField label={l('addressed_to')} error={form.errors.addressed_to} hint={l('addressed_to_hint')}>
              <Input value={form.data.addressed_to} onChange={(event) => form.setData('addressed_to', event.target.value)} />
            </FormField>
            <div className="sm:col-span-3"><Button type="submit" disabled={form.processing}>{l('get')}</Button></div>
          </form>
        )}
        <div className="divide-y">
          {letters.length === 0 && <p className="text-muted-foreground">{l('none')}</p>}
          {letters.map((letter) => (
            <div key={letter.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
              <span>
                <span className="font-medium">{t(`hr.letter_kind.${letter.kind}`)}</span>
                <span className="text-muted-foreground"> · {letter.addressed_to ?? l('to_whom')} · {formatDate(letter.issued_at, locale)}</span>
              </span>
              <span className="flex items-center gap-2">
                {!letter.valid && <Badge variant="destructive">{l('revoked_badge')}</Badge>}
                <Button asChild size="icon-sm" variant="ghost" aria-label={l('pdf_open')}><a href={`/me/letters/${letter.id}/pdf`} target="_blank" rel="noreferrer"><FileText /></a></Button>
              </span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
