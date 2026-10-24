import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Copy, LockOpen, Lock, Pencil, Plus, ShoppingCart } from 'lucide-react';
import { toast } from 'sonner';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import SwitchField from '@/components/platform/SwitchField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';

interface Register {
  id: number; name: string; branch_id: number; branch: string | null; einvoice_unit_id: number | null; is_active: boolean; menu_url: string;
  open_shift: { id: number; cashier: string; opened_at: string; opening_cash: string } | null;
}
interface Cashier { id: number; company_id: number; employee_id: number | null; name: string; is_active: boolean; [key: string]: unknown }
interface ShiftRow { id: number; register: string; cashier: string; opened_at: string; closed_at: string; expected_cash: string; counted_cash: string; difference: string; orders_count: number; sales_total: string; [key: string]: unknown }

interface Props {
  registers: Register[];
  cashiers: Cashier[];
  branches: ScopedOption[];
  companies: { value: number; label: string }[];
  units: ScopedOption[];
  staff: ScopedOption[];
  recent_shifts: ShiftRow[];
}

const f = (t: ReturnType<typeof useT>, field: string) => t(`pos.registers.fields.${field}`);

function RegisterDialog({ register, branches, units }: { register?: Register; branches: ScopedOption[]; units: ScopedOption[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ branch_id: register?.branch_id ?? branches[0]?.value ?? '', name: register?.name ?? '', einvoice_unit_id: register?.einvoice_unit_id ?? '', is_active: register?.is_active ?? true });
  const companyId = branches.find((branch) => branch.value === Number(form.data.branch_id))?.company_id;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    form.transform((data) => ({ ...data, einvoice_unit_id: data.einvoice_unit_id || null }));
    register ? form.put(url(`/pos/registers/${register.id}`), options) : form.post(url('/pos/registers'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{register ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('pos.registers.new')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{register ? register.name : t('pos.registers.new')}</DialogTitle></DialogHeader>
          {!register && <FormField label={f(t, 'branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', Number(value))} options={branches} /></FormField>}
          <FormField label={f(t, 'name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={f(t, 'unit')} error={form.errors.einvoice_unit_id} hint={!form.data.einvoice_unit_id ? t('pos.registers.no_unit') : undefined}>
            <SelectInput value={form.data.einvoice_unit_id} onChange={(value) => form.setData('einvoice_unit_id', value === '' ? '' : Number(value))} options={units.filter((unit) => unit.company_id === companyId)} emptyLabel="—" />
          </FormField>
          <SwitchField id="register-active" label={f(t, 'is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CashierDialog({ cashier, companies, staff }: { cashier?: Cashier; companies: { value: number; label: string }[]; staff: ScopedOption[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ company_id: cashier?.company_id ?? companies[0]?.value ?? '', employee_id: cashier?.employee_id ?? '', name: cashier?.name ?? '', pin: '', is_active: cashier?.is_active ?? true });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset('pin'); } };
    form.transform((data) => ({ ...data, employee_id: data.employee_id || null, pin: data.pin || null }));
    cashier ? form.put(url(`/pos/cashiers/${cashier.id}`), options) : form.post(url('/pos/cashiers'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{cashier ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm" variant="outline"><Plus data-icon="inline-start" />{t('pos.registers.new_cashier')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{cashier ? cashier.name : t('pos.registers.new_cashier')}</DialogTitle></DialogHeader>
          {!cashier && companies.length > 1 && <FormField label={t('pos.catalog.fields.company')}><SelectInput value={form.data.company_id} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>}
          <FormField label={f(t, 'employee')} error={form.errors.employee_id}>
            <SelectInput
              value={form.data.employee_id}
              onChange={(value) => {
                const employee = staff.find((item) => item.value === Number(value));
                form.setData((data) => ({ ...data, employee_id: value === '' ? '' : Number(value), name: employee && !data.name ? employee.label : data.name }));
              }}
              options={staff.filter((item) => item.company_id === Number(form.data.company_id))}
              emptyLabel={t('pos.registers.no_employee')}
            />
          </FormField>
          <FormField label={f(t, 'name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={f(t, 'pin')} error={form.errors.pin}><Input dir="ltr" type="password" inputMode="numeric" autoComplete="new-password" value={form.data.pin} onChange={(event) => form.setData('pin', event.target.value)} /></FormField>
          <SwitchField id="cashier-active" label={f(t, 'is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function OpenShiftDialog({ register, cashiers }: { register: Register; cashiers: Cashier[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ cashier_id: cashiers[0]?.id ?? '', pin: '', opening_cash: '0' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(url(`/pos/registers/${register.id}/shifts`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><LockOpen data-icon="inline-start" />{t('pos.registers.open_shift')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('pos.registers.open_shift')} · {register.name}</DialogTitle></DialogHeader>
          <FormField label={f(t, 'cashier')} error={form.errors.cashier_id}><SelectInput value={form.data.cashier_id} onChange={(value) => form.setData('cashier_id', Number(value))} options={cashiers.filter((cashier) => cashier.is_active).map((cashier) => ({ value: cashier.id, label: cashier.name }))} /></FormField>
          <FormField label={f(t, 'pin')} error={form.errors.pin}><Input dir="ltr" type="password" inputMode="numeric" autoComplete="off" value={form.data.pin} onChange={(event) => form.setData('pin', event.target.value)} /></FormField>
          <FormField label={f(t, 'opening_cash')} error={form.errors.opening_cash}><Input dir="ltr" inputMode="decimal" value={form.data.opening_cash} onChange={(event) => form.setData('opening_cash', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('pos.registers.open_shift')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CloseShiftDialog({ register }: { register: Register }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ counted_cash: '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.put(url(`/pos/shifts/${register.open_shift!.id}/close`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="ghost"><Lock data-icon="inline-start" />{t('pos.registers.close_shift')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('pos.registers.close_shift')} · {register.name}</DialogTitle></DialogHeader>
          <FormField label={f(t, 'counted_cash')} error={form.errors.counted_cash}><Input dir="ltr" inputMode="decimal" value={form.data.counted_cash} onChange={(event) => form.setData('counted_cash', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('pos.registers.close_shift')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function RegistersIndex({ registers, cashiers, branches, companies, units, staff, recent_shifts: shifts }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const companyOf = (register: Register) => branches.find((branch) => branch.value === register.branch_id)?.company_id;

  function copy(link: string) {
    navigator.clipboard?.writeText(link).then(() => toast.success(t('pos.registers.copied')));
  }

  return (
    <SuiteLayout title={t('pos.registers.title')}>
      <PageHeader title={t('pos.registers.title')} actions={can('pos.registers.create') && <RegisterDialog branches={branches} units={units} />} />
      {registers.length === 0 ? <StatusMessage>{t('pos.registers.none')}</StatusMessage> : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {registers.map((register) => (
            <Card key={register.id} className={register.is_active ? undefined : 'opacity-60'}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2 text-base">
                  <span>{register.name} <span className="text-xs font-normal text-muted-foreground">{register.branch}</span></span>
                  {can('pos.registers.update') && <RegisterDialog register={register} branches={branches} units={units} />}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-2 text-sm">
                {register.open_shift
                  ? <Badge variant="outline" className="self-start border-transparent bg-emerald-100 text-emerald-800">{t('pos.registers.shift_by', { time: formatDate(register.open_shift.opened_at, locale), cashier: register.open_shift.cashier })}</Badge>
                  : <span className="text-muted-foreground">{t('pos.registers.no_shift')}</span>}
                {!register.einvoice_unit_id && <span className="text-xs text-amber-700">{t('pos.registers.no_unit')}</span>}
              </CardContent>
              <CardFooter className="flex flex-wrap gap-2">
                {register.open_shift && can('pos.orders.sell') && <Button size="sm" asChild><Link href={url(`/pos/sell/${register.id}`)}><ShoppingCart data-icon="inline-start" />{t('pos.registers.sell')}</Link></Button>}
                {!register.open_shift && can('pos.registers.open_shift') && <OpenShiftDialog register={register} cashiers={cashiers.filter((cashier) => cashier.company_id === companyOf(register))} />}
                {register.open_shift && can('pos.registers.close_shift') && <CloseShiftDialog register={register} />}
                <Button size="sm" variant="ghost" onClick={() => copy(register.menu_url)}><Copy data-icon="inline-start" />{t('pos.registers.copy_link')}</Button>
              </CardFooter>
            </Card>
          ))}
        </div>
      )}

      <PageHeader title={t('pos.registers.cashiers')} actions={can('pos.registers.create') && <CashierDialog companies={companies} staff={staff} />} />
      <DataTable<Cashier>
        rows={cashiers}
        empty={t('pos.catalog.none')}
        columns={[
          { key: 'name', label: f(t, 'name'), render: (cashier) => <span className={cashier.is_active ? undefined : 'text-muted-foreground line-through'}>{cashier.name}</span> },
          { key: 'employee', label: f(t, 'employee'), render: (cashier) => staff.find((item) => item.value === cashier.employee_id)?.label ?? '—' },
          { key: 'actions', label: '', className: 'text-end', render: (cashier) => can('pos.registers.update') && <CashierDialog cashier={cashier} companies={companies} staff={staff} /> },
        ]}
      />

      <PageHeader title={t('pos.registers.recent_shifts')} />
      <DataTable<ShiftRow>
        rows={shifts}
        empty={t('pos.catalog.none')}
        columns={[
          { key: 'register', label: f(t, 'name'), render: (shift) => <span><span className="block font-medium">{shift.register}</span><span className="text-xs text-muted-foreground">{shift.cashier}</span></span> },
          { key: 'opened_at', label: f(t, 'opened_at'), render: (shift) => formatDate(shift.opened_at, locale) },
          { key: 'sales', label: f(t, 'sales'), render: (shift) => <span className="tabular-nums">{formatMoney(shift.sales_total, locale)} <span className="text-xs text-muted-foreground">({shift.orders_count})</span></span> },
          { key: 'expected', label: f(t, 'expected_cash'), render: (shift) => <span className="tabular-nums">{formatMoney(shift.expected_cash, locale)}</span> },
          { key: 'counted', label: f(t, 'counted_cash'), render: (shift) => <span className="tabular-nums">{formatMoney(shift.counted_cash, locale)}</span> },
          { key: 'difference', label: f(t, 'difference'), render: (shift) => <span className={Number(shift.difference) < 0 ? 'text-destructive tabular-nums' : 'tabular-nums'}>{formatMoney(shift.difference, locale)}</span> },
        ]}
      />
    </SuiteLayout>
  );
}
