import { router, useForm } from '@inertiajs/react';
import { QrCode, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

interface CustomerRow { id: number; name: string; phone: string; email: string | null; wallet_balance: string; registrations_count: number; [key: string]: unknown }
interface BranchOption extends Option { scope: string }
interface JoinCode { branch: string; url: string; qr: string }

interface Props {
  customers: Paginated<CustomerRow>;
  filters: Filters;
  branches: BranchOption[];
  can_register: boolean;
  join_codes?: JoinCode[];
}

function RegisterDialog({ branches }: { branches: BranchOption[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const c = (field: string) => t(`suite.customers.${field}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ phone: '', name: '', email: '', birth_date: '', gender: '', branch_id: branches.length === 1 ? String(branches[0].value) : '', marketing_consent: false });
  const branch = branches.find((option) => String(option.value) === form.data.branch_id);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><UserPlus />{c('register')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{c('register')}</DialogTitle>
          <DialogDescription>{c('register_hint')}</DialogDescription>
        </DialogHeader>
        <form
          id="register-client"
          className="grid gap-3 sm:grid-cols-2"
          onSubmit={(event) => { event.preventDefault(); form.post(url('/customers'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}
        >
          <FormField label={c('phone')} error={form.errors.phone}><Input dir="ltr" inputMode="tel" autoFocus value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></FormField>
          <FormField label={c('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={c('email')} error={form.errors.email}><Input type="email" dir="ltr" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
          <FormField label={c('birth_date')} error={form.errors.birth_date}><Input type="date" dir="ltr" value={form.data.birth_date} onChange={(event) => form.setData('birth_date', event.target.value)} /></FormField>
          <FormField label={c('gender')} error={form.errors.gender}>
            <SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} emptyLabel="—" options={['male', 'female'].map((value) => ({ value, label: c(`genders.${value}`) }))} />
          </FormField>
          <FormField label={c('branch')} error={form.errors.branch_id} hint={branch ? `${c('registrations')}: ${c(`scopes.${branch.scope}`)}` : undefined}>
            <SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} />
          </FormField>
          <label className="flex items-center gap-2 text-sm sm:col-span-2">
            <Checkbox checked={form.data.marketing_consent} onCheckedChange={(checked) => form.setData('marketing_consent', checked === true)} />
            {c('consent')}
          </label>
        </form>
        <DialogFooter><Button type="submit" form="register-client" disabled={form.processing || form.data.branch_id === ''}>{c('register')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function JoinCodesDialog({ codes }: { codes?: JoinCode[] }) {
  const t = useT();
  const c = (field: string) => t(`suite.customers.${field}`);

  return (
    <Dialog onOpenChange={(open) => { if (open && codes === undefined) { router.reload({ only: ['join_codes'] }); } }}>
      <DialogTrigger asChild><Button variant="outline"><QrCode />{c('join_codes')}</Button></DialogTrigger>
      <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{c('join_codes')}</DialogTitle>
          <DialogDescription>{c('join_codes_hint')}</DialogDescription>
        </DialogHeader>
        {codes === undefined && <div className="grid gap-3 sm:grid-cols-2">{[0, 1].map((key) => <div key={key} className="aspect-square animate-pulse rounded-lg bg-muted" />)}</div>}
        <div className="grid gap-3 sm:grid-cols-2">
          {codes?.map((code) => (
            <div key={code.url} className="flex flex-col items-center gap-2 rounded-lg border p-3 text-center">
              <img src={code.qr} alt="" className="size-44 bg-white p-2" />
              <div className="text-sm font-medium">{code.branch}</div>
              <Button size="sm" variant="ghost" onClick={() => { void navigator.clipboard?.writeText(code.url); toast.success(c('copied')); }}>{c('copy_link')}</Button>
            </div>
          ))}
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default function CustomersIndex({ customers, filters: initialFilters, branches, can_register: canRegister, join_codes: joinCodes }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const c = (field: string) => t(`suite.customers.${field}`);

  return (
    <SuiteLayout title={c('title')}>
      <PageHeader
        title={c('title')}
        description={c('lede')}
        actions={canRegister && branches.length > 0 ? <><JoinCodesDialog codes={joinCodes} /><RegisterDialog branches={branches} /></> : null}
      />
      <FilterBar filters={filters} setFilter={setFilter} selects={branches.length > 1 ? [{ key: 'branch_id', label: c('branch'), options: branches }] : []} />
      <DataTable<CustomerRow>
        rows={customers}
        rowHref={(customer) => url(`/customers/${customer.id}`)}
        empty={c('none')}
        columns={[
          { key: 'name', label: c('name'), render: (customer) => <span className="font-medium">{customer.name}</span> },
          { key: 'phone', label: c('phone'), render: (customer) => <span dir="ltr">+{customer.phone}</span> },
          { key: 'email', label: c('email'), render: (customer) => <span dir="ltr" className="text-muted-foreground">{customer.email}</span> },
          { key: 'registrations_count', label: c('registrations'), render: (customer) => <Badge variant="outline">{customer.registrations_count}</Badge> },
          { key: 'wallet_balance', label: c('balance'), render: (customer) => formatMoney(customer.wallet_balance, locale) },
        ]}
      />
    </SuiteLayout>
  );
}
