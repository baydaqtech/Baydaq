import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface Line { description: string; quantity: string; unit_price: string; vat_rate: string; account_id: string | number | null; net?: string }
interface Supplier { id: number; name: string; vat_number: string | null; cr_number: string | null; phone: string | null; email: string | null; payment_days: number; is_active: boolean }
interface Order { id: number; number: string; status: string; supplier: string; branch: string | null; order_date: string; expected_on: string | null; total: string; lines: Line[] }
interface Bill {
  id: number; status: string; supplier: string; branch: string | null; supplier_invoice_no: string | null; bill_date: string; due_on: string; overdue: boolean;
  subtotal: string; vat: string; total: string; balance: string; lines: Line[];
}

interface Props {
  suppliers: Supplier[];
  orders: Order[];
  bills: Bill[];
  accounts: Option[];
  branches: Option[];
  can: { purchase: boolean; approve_orders: boolean; bill: boolean; approve_bills: boolean; pay: boolean };
}

function SupplierDialog({ supplier }: { supplier?: Supplier }) {
  const t = useT();
  const url = useSuiteUrl();
  const p = (key: string) => t(`finance.purchasing.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ name: supplier?.name ?? '', vat_number: supplier?.vat_number ?? '', cr_number: supplier?.cr_number ?? '', phone: supplier?.phone ?? '', email: supplier?.email ?? '', iban: '', payment_days: String(supplier?.payment_days ?? 30), is_active: supplier?.is_active ?? true });
  const submit = () => (supplier ? form.put(url(`/finance/suppliers/${supplier.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) }) : form.post(url('/finance/suppliers'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }));
  const field = (key: 'name' | 'vat_number' | 'cr_number' | 'phone' | 'email' | 'iban' | 'payment_days', dir?: 'ltr') => (
    <FormField label={p(key)} error={form.errors[key]}><Input dir={dir} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} /></FormField>
  );

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{supplier ? <button type="button" className="font-medium hover:underline">{supplier.name}</button> : <Button size="sm" variant="outline"><Plus />{p('new_supplier')}</Button>}</DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{supplier?.name ?? p('new_supplier')}</DialogTitle></DialogHeader>
        <form id="supplier-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); submit(); }}>
          {field('name')}{field('vat_number', 'ltr')}{field('cr_number', 'ltr')}{field('phone', 'ltr')}{field('email', 'ltr')}{field('iban', 'ltr')}{field('payment_days', 'ltr')}
        </form>
        <DialogFooter><Button type="submit" form="supplier-form" disabled={form.processing}>{p('save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function DocumentDialog({ kind, suppliers, branches, accounts }: { kind: 'order' | 'bill'; suppliers: Supplier[]; branches: Option[]; accounts: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const p = (key: string) => t(`finance.purchasing.${key}`);
  const [open, setOpen] = useState(false);
  const today = new Date().toISOString().slice(0, 10);
  const blank: Line = { description: '', quantity: '1', unit_price: '', vat_rate: '15', account_id: '' };
  const form = useForm({ branch_id: String(branches[0]?.value ?? ''), supplier_id: '', order_date: today, bill_date: today, expected_on: '', supplier_invoice_no: '', lines: [blank] });
  const errors = form.errors as Record<string, string>;
  const setLine = (index: number, patch: Partial<Line>) => form.setData('lines', form.data.lines.map((line, at) => (at === index ? { ...line, ...patch } : line)));
  const total = form.data.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0) * (1 + Number(line.vat_rate) / 100), 0);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Plus />{p(kind === 'order' ? 'new_order' : 'new_bill')}</Button></DialogTrigger>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader><DialogTitle>{p(kind === 'order' ? 'new_order' : 'new_bill')}</DialogTitle></DialogHeader>
        <form id={`${kind}-form`} className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.post(url(kind === 'order' ? '/finance/orders' : '/finance/bills'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}>
          <div className="grid gap-3 sm:grid-cols-4">
            <FormField label={p('supplier')} error={errors.supplier_id}><SelectInput value={form.data.supplier_id} onChange={(value) => form.setData('supplier_id', value)} options={suppliers.filter((supplier) => supplier.is_active).map((supplier) => ({ value: supplier.id, label: supplier.name }))} /></FormField>
            <FormField label={p('branch')} error={errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>
            {kind === 'order'
              ? <FormField label={p('order_date')} error={errors.order_date}><Input type="date" dir="ltr" value={form.data.order_date} onChange={(event) => form.setData('order_date', event.target.value)} /></FormField>
              : <FormField label={p('bill_date')} error={errors.bill_date}><Input type="date" dir="ltr" value={form.data.bill_date} onChange={(event) => form.setData('bill_date', event.target.value)} /></FormField>}
            {kind === 'order'
              ? <FormField label={p('expected_on')}><Input type="date" dir="ltr" value={form.data.expected_on} onChange={(event) => form.setData('expected_on', event.target.value)} /></FormField>
              : <FormField label={p('supplier_invoice_no')}><Input dir="ltr" value={form.data.supplier_invoice_no} onChange={(event) => form.setData('supplier_invoice_no', event.target.value)} /></FormField>}
          </div>
          {form.data.lines.map((line, index) => (
            <div key={index} className="grid grid-cols-2 gap-2 sm:grid-cols-[1.5fr_4rem_6rem_4.5rem_1.3fr_auto]">
              <Input placeholder={p('description')} value={line.description} onChange={(event) => setLine(index, { description: event.target.value })} />
              <Input type="number" min="0" step="0.001" dir="ltr" aria-label={p('quantity')} value={line.quantity} onChange={(event) => setLine(index, { quantity: event.target.value })} />
              <Input type="number" min="0" step="0.01" dir="ltr" placeholder={p('unit_price')} value={line.unit_price} onChange={(event) => setLine(index, { unit_price: event.target.value })} />
              <SelectInput value={line.vat_rate} onChange={(value) => setLine(index, { vat_rate: value })} options={['15', '5', '0'].map((value) => ({ value, label: `${value}%` }))} />
              <SelectInput value={String(line.account_id ?? '')} onChange={(value) => setLine(index, { account_id: value })} options={accounts} placeholder={p('account')} />
              <Button type="button" size="icon" variant="ghost" disabled={form.data.lines.length <= 1} onClick={() => form.setData('lines', form.data.lines.filter((_, at) => at !== index))}><Trash2 /></Button>
            </div>
          ))}
          <div className="flex items-center justify-between text-sm">
            <Button type="button" size="sm" variant="outline" onClick={() => form.setData('lines', [...form.data.lines, blank])}><Plus />{p('add_line')}</Button>
            <span className="font-medium">{p('total')}: {formatMoney(total, locale)}</span>
          </div>
          {Object.keys(errors).filter((key) => key.startsWith('lines')).slice(0, 1).map((key) => <p key={key} className="text-sm text-destructive">{errors[key]}</p>)}
        </form>
        <DialogFooter><Button type="submit" form={`${kind}-form`} disabled={form.processing || form.data.supplier_id === ''}>{p('save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function PayDialog({ bill }: { bill: Bill }) {
  const t = useT();
  const url = useSuiteUrl();
  const p = (key: string) => t(`finance.purchasing.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ amount: bill.balance, paid_on: new Date().toISOString().slice(0, 10), method: 'bank', reference: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm">{p('pay')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{p('pay')} · {bill.supplier}</DialogTitle></DialogHeader>
        <form id={`pay-${bill.id}`} className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url(`/finance/bills/${bill.id}/payments`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
          <FormField label={p('amount')} error={form.errors.amount}><Input type="number" step="0.01" dir="ltr" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>
          <FormField label={p('paid_on')} error={form.errors.paid_on}><Input type="date" dir="ltr" value={form.data.paid_on} onChange={(event) => form.setData('paid_on', event.target.value)} /></FormField>
          <FormField label={p('method')}><SelectInput value={form.data.method} onChange={(value) => form.setData('method', value)} options={['bank', 'cash'].map((value) => ({ value, label: p(`methods.${value}`) }))} /></FormField>
          <FormField label={p('reference')}><Input dir="ltr" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form={`pay-${bill.id}`} disabled={form.processing}>{p('pay')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Suppliers, purchase orders and supplier bills: from ordering to paying. */
export default function Purchasing({ suppliers, orders, bills, accounts, branches, can }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const p = (key: string) => t(`finance.purchasing.${key}`);
  const moveOrder = (order: Order, status: string) => router.put(url(`/finance/orders/${order.id}/status`), { status }, { preserveScroll: true });

  return (
    <SuiteLayout title={p('title')}>
      <PageHeader title={p('title')} description={p('lede')} />
      <Tabs defaultValue="bills">
        <TabsList>
          <TabsTrigger value="bills">{p('bills')}</TabsTrigger>
          <TabsTrigger value="orders">{p('orders')}</TabsTrigger>
          <TabsTrigger value="suppliers">{p('suppliers')}</TabsTrigger>
        </TabsList>
        <TabsContent value="bills" className="flex flex-col gap-3">
          {can.bill && suppliers.length > 0 && <div><DocumentDialog kind="bill" suppliers={suppliers} branches={branches} accounts={accounts} /></div>}
          <Card><CardContent className="divide-y">
            {bills.length === 0 && <p className="text-sm text-muted-foreground">{p('none')}</p>}
            {bills.map((bill) => (
              <div key={bill.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2"><span className="font-medium">{bill.supplier}</span>{bill.supplier_invoice_no && <span className="text-xs text-muted-foreground" dir="ltr">{bill.supplier_invoice_no}</span>}<Badge variant={bill.overdue ? 'destructive' : 'outline'}>{bill.overdue ? p('overdue') : p(`bill_statuses.${bill.status}`)}</Badge></div>
                  <div className="text-xs text-muted-foreground">{bill.branch} · {formatDate(bill.bill_date, locale)} · {p('due_on')} {formatDate(bill.due_on, locale)}</div>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-end"><span className="font-medium">{formatMoney(bill.total, locale)}</span>{bill.status === 'approved' && <span className="block text-xs text-muted-foreground">{p('balance')}: {formatMoney(bill.balance, locale)}</span>}</span>
                  {bill.status === 'draft' && can.approve_bills && <Button size="sm" variant="outline" onClick={() => router.put(url(`/finance/bills/${bill.id}/approve`), {}, { preserveScroll: true })}>{p('approve')}</Button>}
                  {bill.status === 'approved' && can.pay && <PayDialog bill={bill} />}
                </div>
              </div>
            ))}
          </CardContent></Card>
        </TabsContent>
        <TabsContent value="orders" className="flex flex-col gap-3">
          {can.purchase && suppliers.length > 0 && <div><DocumentDialog kind="order" suppliers={suppliers} branches={branches} accounts={accounts} /></div>}
          <Card><CardContent className="divide-y">
            {orders.length === 0 && <p className="text-sm text-muted-foreground">{p('none')}</p>}
            {orders.map((order) => (
              <div key={order.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <div>
                  <div className="flex items-center gap-2"><span className="font-mono">{order.number}</span><span className="font-medium">{order.supplier}</span><Badge variant="outline">{p(`order_statuses.${order.status}`)}</Badge></div>
                  <div className="text-xs text-muted-foreground">{order.branch} · {formatDate(order.order_date, locale)} · {order.lines.map((line) => line.description).join('، ')}</div>
                </div>
                <div className="flex items-center gap-1">
                  <span className="me-2 font-medium">{formatMoney(order.total, locale)}</span>
                  {order.status === 'draft' && can.approve_orders && <Button size="sm" variant="outline" onClick={() => moveOrder(order, 'approved')}>{p('approve')}</Button>}
                  {order.status === 'approved' && can.purchase && <Button size="sm" variant="outline" onClick={() => moveOrder(order, 'received')}>{p('receive')}</Button>}
                  {order.status === 'received' && can.bill && <Button size="sm" onClick={() => moveOrder(order, 'billed')}>{p('to_bill')}</Button>}
                  {['draft', 'approved', 'received'].includes(order.status) && can.purchase && <Button size="sm" variant="ghost" onClick={() => moveOrder(order, 'cancelled')}>{p('cancel')}</Button>}
                </div>
              </div>
            ))}
          </CardContent></Card>
        </TabsContent>
        <TabsContent value="suppliers" className="flex flex-col gap-3">
          {(can.purchase || can.bill) && <div><SupplierDialog /></div>}
          <Card><CardContent className="divide-y">
            {suppliers.length === 0 && <p className="text-sm text-muted-foreground">{p('none')}</p>}
            {suppliers.map((supplier) => (
              <div key={supplier.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                {(can.purchase || can.bill) ? <SupplierDialog supplier={supplier} /> : <span className="font-medium">{supplier.name}</span>}
                <span className="text-xs text-muted-foreground" dir="ltr">{[supplier.vat_number, supplier.phone].filter(Boolean).join(' · ')}</span>
              </div>
            ))}
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
