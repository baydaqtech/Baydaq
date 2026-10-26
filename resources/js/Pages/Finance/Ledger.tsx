import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import Paginator from '@/components/platform/Paginator';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { LaravelPage, Option } from '@/types/resources';

interface Account { id: number; code: string; name: string; name_ar: string; name_en: string; type: string; system_key: string | null; is_active: boolean }

interface Entry {
  id: number; date: string; source: string; memo: string | null; is_reversal: boolean; company: string | null; branch: string | null;
  lines: { account: string; debit: string; credit: string }[];
}

interface Props {
  accounts: Account[];
  entries: LaravelPage<Entry>;
  filters: Filters;
  types: string[];
  can_edit: boolean;
  can_post: boolean;
  branches: Option[];
}

const SOURCES = ['manual', 'pos.order', 'invoicing.invoice', 'invoicing.payment', 'hr.payroll', 'hr.payroll_payment', 'hr.expense', 'hr.expense_payment', 'finance.bill', 'finance.payment'];

function AccountDialog({ account, types }: { account?: Account; types: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const l = (key: string) => t(`finance.ledger.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ code: account?.code ?? '', name_ar: account?.name_ar ?? '', name_en: account?.name_en ?? '', type: account?.type ?? 'expense', is_active: account?.is_active ?? true });
  const submit = () => (account ? form.put(url(`/finance/accounts/${account.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) }) : form.post(url('/finance/accounts'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{account ? <button type="button" className="text-start hover:underline">{account.name}</button> : <Button size="sm" variant="outline"><Plus />{l('new_account')}</Button>}</DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{account ? account.name : l('new_account')}</DialogTitle></DialogHeader>
        <form id="account-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); submit(); }}>
          <FormField label={l('code')} error={form.errors.code}><Input dir="ltr" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></FormField>
          <FormField label={l('type')} error={form.errors.type}><SelectInput value={form.data.type} onChange={(value) => form.setData('type', value)} options={types.map((value) => ({ value, label: t(`finance.types.${value}`) }))} /></FormField>
          <FormField label={l('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={l('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="account-form" disabled={form.processing}>{t('finance.purchasing.save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function EntryDialog({ accounts, branches }: { accounts: Account[]; branches: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const l = (key: string) => t(`finance.ledger.${key}`);
  const [open, setOpen] = useState(false);
  const blank = { account_id: '', debit: '', credit: '' };
  const form = useForm({ branch_id: String(branches[0]?.value ?? ''), entry_date: new Date().toISOString().slice(0, 10), memo: '', lines: [blank, blank] });
  const errors = form.errors as Record<string, string>;
  const total = (side: 'debit' | 'credit') => form.data.lines.reduce((sum, line) => sum + Number(line[side] || 0), 0);
  const setLine = (index: number, patch: Partial<typeof blank>) => form.setData('lines', form.data.lines.map((line, at) => (at === index ? { ...line, ...patch } : line)));
  const options = accounts.filter((account) => account.is_active).map((account) => ({ value: account.id, label: `${account.code} ${account.name}` }));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus />{l('new_entry')}</Button></DialogTrigger>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader><DialogTitle>{l('new_entry')}</DialogTitle></DialogHeader>
        <form id="entry-form" className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.post(url('/finance/journal'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}>
          <div className="grid gap-3 sm:grid-cols-3">
            <FormField label={l('branch')} error={errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>
            <FormField label={l('date')} error={errors.entry_date}><Input type="date" dir="ltr" value={form.data.entry_date} onChange={(event) => form.setData('entry_date', event.target.value)} /></FormField>
            <FormField label={l('memo')} error={errors.memo}><Input value={form.data.memo} onChange={(event) => form.setData('memo', event.target.value)} /></FormField>
          </div>
          {form.data.lines.map((line, index) => (
            <div key={index} className="grid grid-cols-[1fr_7rem_7rem_auto] gap-2">
              <SelectInput value={line.account_id} onChange={(value) => setLine(index, { account_id: value })} options={options} placeholder={l('account')} />
              <Input type="number" step="0.01" min="0" dir="ltr" placeholder={l('debit')} value={line.debit} onChange={(event) => setLine(index, { debit: event.target.value, credit: '' })} />
              <Input type="number" step="0.01" min="0" dir="ltr" placeholder={l('credit')} value={line.credit} onChange={(event) => setLine(index, { credit: event.target.value, debit: '' })} />
              <Button type="button" size="icon" variant="ghost" disabled={form.data.lines.length <= 2} onClick={() => form.setData('lines', form.data.lines.filter((_, at) => at !== index))}><Trash2 /></Button>
            </div>
          ))}
          <div className="flex items-center justify-between text-sm">
            <Button type="button" size="sm" variant="outline" onClick={() => form.setData('lines', [...form.data.lines, blank])}><Plus />{l('add_line')}</Button>
            <span dir="ltr" className={total('debit') === total('credit') ? 'text-muted-foreground' : 'text-destructive'}>{formatMoney(total('debit'), locale)} / {formatMoney(total('credit'), locale)}</span>
          </div>
          {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
        </form>
        <DialogFooter><Button type="submit" form="entry-form" disabled={form.processing || total('debit') === 0 || total('debit') !== total('credit')}>{t('finance.purchasing.save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** The chart of accounts and every journal entry posted by the suite or by hand. */
export default function Ledger({ accounts, entries, filters: initialFilters, types, can_edit: canEdit, can_post: canPost, branches }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);
  const l = (key: string) => t(`finance.ledger.${key}`);

  return (
    <SuiteLayout title={l('title')}>
      <PageHeader title={l('title')} description={l('lede')} actions={canPost && branches.length > 0 ? <EntryDialog accounts={accounts} branches={branches} /> : null} />
      <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2"><CardTitle className="text-base">{l('chart')}</CardTitle>{canEdit && <AccountDialog types={types} />}</CardHeader>
          <CardContent className="divide-y text-sm">
            {types.map((type) => (
              <div key={type} className="py-2">
                <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">{t(`finance.types.${type}`)}</div>
                {accounts.filter((account) => account.type === type).map((account) => (
                  <div key={account.id} className="flex items-center gap-2 py-0.5">
                    <span className="w-12 font-mono text-xs text-muted-foreground" dir="ltr">{account.code}</span>
                    {canEdit ? <AccountDialog account={account} types={types} /> : <span>{account.name}</span>}
                    {!account.is_active && <Badge variant="outline">—</Badge>}
                  </div>
                ))}
              </div>
            ))}
          </CardContent>
        </Card>
        <div className="flex min-w-0 flex-col gap-3">
          <div className="w-64"><SelectInput value={String(filters.source ?? '')} onChange={(value) => setFilter('source', value)} options={SOURCES.map((value) => ({ value, label: t(`finance.sources.${value}`) }))} emptyLabel={l('all_sources')} /></div>
          {entries.data.length === 0 && <p className="text-sm text-muted-foreground">{l('none')}</p>}
          {entries.data.map((entry) => (
            <Card key={entry.id} className="gap-2 py-3">
              <CardContent className="flex flex-col gap-1 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="flex flex-wrap items-center gap-2"><span className="font-medium">{formatDate(entry.date, locale)}</span><Badge variant="outline">{t(`finance.sources.${entry.source}`)}</Badge>{entry.is_reversal && <Badge variant="destructive">{l('reversal')}</Badge>}<span className="text-muted-foreground">{entry.memo}</span></span>
                  <span className="text-xs text-muted-foreground">{[entry.company, entry.branch].filter(Boolean).join(' · ')}</span>
                </div>
                <table className="w-full">
                  <tbody>
                    {entry.lines.map((line, index) => (
                      <tr key={index}>
                        <td className={Number(line.credit) > 0 ? 'ps-6' : ''}>{line.account}</td>
                        <td className="w-28 text-end" dir="ltr">{Number(line.debit) > 0 ? formatMoney(line.debit, locale) : ''}</td>
                        <td className="w-28 text-end" dir="ltr">{Number(line.credit) > 0 ? formatMoney(line.credit, locale) : ''}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </CardContent>
            </Card>
          ))}
          <Paginator page={entries} />
        </div>
      </div>
    </SuiteLayout>
  );
}
