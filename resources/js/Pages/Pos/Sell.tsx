import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Ban, Flame, Minus, Plus, Trash2 } from 'lucide-react';
import ModifierPicker from '@/components/pos/ModifierPicker';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import { cartLineUnitPrice, cartPayload, cartTotal, type CartLine, type MenuCategory, type MenuModifier, type MenuProduct, type OrderResource } from '@/types/pos';
import type { Option } from '@/types/resources';

interface Props {
  register: { id: number; name: string; branch_id: number; shift: { id: number; cashier: string } | null; has_unit: boolean };
  categories: MenuCategory[];
  products: MenuProduct[];
  open_orders: OrderResource[];
  types: Option[];
  methods: Option[];
  staff: Option[];
  can_discount: boolean;
}

interface PaymentRow { method: string; amount: string; reference: string }

function PayDialog({ order, registerId, methods, staff, onClose }: { order: OrderResource; registerId: number; methods: Option[]; staff: Option[]; onClose: () => void }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const form = useForm<{ payments: PaymentRow[]; staff_employee_id: string }>({ payments: [{ method: 'cash', amount: order.total, reference: '' }], staff_employee_id: '' });
  const staffMeal = form.data.payments.some((payment) => payment.method === 'staff_account');
  const [tendered, setTendered] = useState('');
  const paid = form.data.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
  const remaining = Math.round((Number(order.total) - paid) * 100) / 100;
  const cash = form.data.payments.filter((payment) => payment.method === 'cash').reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
  const change = tendered !== '' ? Math.max(0, Number(tendered) - cash) : 0;

  function setPayment(index: number, patch: Partial<PaymentRow>) {
    form.setData('payments', form.data.payments.map((payment, i) => (i === index ? { ...payment, ...patch } : payment)));
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(url(`/pos/sell/${registerId}/orders/${order.id}/pay`), { preserveScroll: true, onSuccess: onClose });
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent dir={dir} className="max-w-lg">
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('pos.sell.pay_order', { number: order.number })}</DialogTitle></DialogHeader>
          <p className="text-3xl font-semibold tabular-nums">{formatMoney(order.total, locale)}</p>
          {form.data.payments.map((payment, index) => (
            <div key={index} className="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
              <FormField><SelectInput value={payment.method} onChange={(method) => setPayment(index, { method })} options={methods} /></FormField>
              <FormField label={t('pos.sell.amount')}><Input dir="ltr" inputMode="decimal" value={payment.amount} onChange={(event) => setPayment(index, { amount: event.target.value })} /></FormField>
              <Button type="button" variant="ghost" size="icon-sm" disabled={form.data.payments.length === 1} onClick={() => form.setData('payments', form.data.payments.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
              {payment.method !== 'cash' && <Input className="col-span-2" dir="ltr" placeholder={t('pos.sell.reference')} value={payment.reference} onChange={(event) => setPayment(index, { reference: event.target.value })} />}
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" className="self-start" onClick={() => form.setData('payments', [...form.data.payments, { method: 'mada', amount: remaining > 0 ? remaining.toFixed(2) : '0', reference: '' }])}><Plus data-icon="inline-start" />{t('pos.sell.add_payment')}</Button>
          {cash > 0 && (
            <FormField label={t('pos.payment_method.cash')} hint={change > 0 ? `${t('pos.sell.change')}: ${formatMoney(change, locale)}` : undefined}>
              <Input dir="ltr" inputMode="decimal" value={tendered} onChange={(event) => setTendered(event.target.value)} />
            </FormField>
          )}
          {remaining !== 0 && <p className="text-sm text-destructive">{t('pos.sell.remaining')}: {formatMoney(remaining, locale)}</p>}
          {staffMeal && <FormField label={t('pos.sell.staff_member')} hint={t('pos.sell.staff_meal_hint')} error={(form.errors as Record<string, string>).staff_employee_id}><SelectInput value={form.data.staff_employee_id} onChange={(value) => form.setData('staff_employee_id', value)} options={staff} /></FormField>}
          {form.errors.payments && <p className="text-sm text-destructive">{form.errors.payments}</p>}
          <DialogFooter><Button type="submit" disabled={form.processing || remaining !== 0}>{t('pos.sell.pay')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Sell({ register, categories, products, open_orders: openOrders, types, methods, staff, can_discount: canDiscount }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [category, setCategory] = useState<number | null>(null);
  const [cart, setCart] = useState<CartLine[]>([]);
  const [picking, setPicking] = useState<MenuProduct | null>(null);
  const [details, setDetails] = useState({ type: 'takeaway', table_no: '', customer_name: '', discount: '' });
  const [payingId, setPayingId] = useState<number | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [sending, setSending] = useState(false);

  const shown = useMemo(() => (category === null ? products : products.filter((product) => product.category_id === category)), [category, products]);
  const total = cartTotal(cart) - Number(details.discount || 0);
  const paying = openOrders.find((order) => order.id === payingId) ?? null;

  function add(product: MenuProduct, modifiers: MenuModifier[] = []) {
    const key = `${product.id}:${modifiers.map((modifier) => modifier.id).sort().join(',')}`;
    setCart((current) => (current.some((line) => line.key === key) ? current.map((line) => (line.key === key ? { ...line, quantity: line.quantity + 1 } : line)) : [...current, { key, product, modifiers, quantity: 1 }]));
    setPicking(null);
  }

  function setQuantity(key: string, quantity: number) {
    setCart((current) => (quantity <= 0 ? current.filter((line) => line.key !== key) : current.map((line) => (line.key === key ? { ...line, quantity } : line))));
  }

  function send(payNow: boolean) {
    setSending(true);
    router.post(url(`/pos/sell/${register.id}/orders`), { ...details, discount: details.discount || null, lines: cartPayload(cart) }, {
      preserveScroll: true,
      onError: (failed) => setErrors(failed),
      onSuccess: (page) => {
        setErrors({});
        setCart([]);
        setDetails({ type: 'takeaway', table_no: '', customer_name: '', discount: '' });
        const orderId = (page.flash as { order_id?: number } | undefined)?.order_id;
        if (payNow && orderId) {
          setPayingId(orderId);
        }
      },
      onFinish: () => setSending(false),
    });
  }

  if (!register.shift) {
    return (
      <SuiteLayout title={`${t('pos.sell.title')} · ${register.name}`}>
        <StatusMessage>{t('pos.sell.need_shift')}</StatusMessage>
        <Button asChild className="mt-4"><Link href={url('/pos/registers')}>{t('pos.registers.title')}</Link></Button>
      </SuiteLayout>
    );
  }

  return (
    <SuiteLayout title={`${t('pos.sell.title')} · ${register.name}`}>
      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <section className="flex flex-col gap-3">
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant={category === null ? 'default' : 'outline'} onClick={() => setCategory(null)}>{t('pos.sell.all')}</Button>
            {categories.map((item) => (
              <Button key={item.id} size="sm" variant={category === item.id ? 'default' : 'outline'} onClick={() => setCategory(item.id)} style={item.color && category !== item.id ? { borderColor: item.color } : undefined}>{item.name}</Button>
            ))}
          </div>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
            {shown.map((product) => (
              <button key={product.id} type="button" onClick={() => (product.modifier_groups.length ? setPicking(product) : add(product))} className="flex min-h-24 flex-col justify-between rounded-lg border bg-card p-3 text-start transition hover:border-brass hover:shadow-sm">
                <span className="font-medium leading-tight">{product.name}</span>
                <span className="flex items-center justify-between text-sm text-muted-foreground">
                  <span className="tabular-nums">{formatMoney(product.price, locale)}</span>
                  {product.calories !== null && <span className="flex items-center gap-0.5 text-xs"><Flame className="size-3" />{product.calories}</span>}
                </span>
              </button>
            ))}
          </div>
        </section>

        <aside className="flex flex-col gap-4">
          <Card>
            <CardHeader><CardTitle className="flex items-center justify-between text-base">{t('pos.sell.cart')}<span className="text-xs font-normal text-muted-foreground">{register.shift.cashier}</span></CardTitle></CardHeader>
            <CardContent className="flex flex-col gap-3">
              {!register.has_unit && <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-800">{t('pos.registers.no_unit')}</p>}
              {cart.length === 0 ? <p className="text-sm text-muted-foreground">{t('pos.sell.empty_cart')}</p> : (
                <ul className="flex flex-col divide-y">
                  {cart.map((line) => (
                    <li key={line.key} className="flex items-center gap-2 py-2 text-sm">
                      <span className="flex-1">
                        <span className="block font-medium">{line.product.name}</span>
                        {line.modifiers.length > 0 && <span className="block text-xs text-muted-foreground">{line.modifiers.map((modifier) => modifier.name).join('، ')}</span>}
                      </span>
                      <Button size="icon-xs" variant="outline" onClick={() => setQuantity(line.key, line.quantity - 1)} aria-label="-"><Minus /></Button>
                      <span className="w-6 text-center tabular-nums">{line.quantity}</span>
                      <Button size="icon-xs" variant="outline" onClick={() => setQuantity(line.key, line.quantity + 1)} aria-label="+"><Plus /></Button>
                      <span className="w-20 text-end tabular-nums">{formatMoney(cartLineUnitPrice(line) * line.quantity, locale)}</span>
                    </li>
                  ))}
                </ul>
              )}
              <div className="grid grid-cols-2 gap-2">
                <FormField label={t('pos.sell.type')}><SelectInput value={details.type} onChange={(type) => setDetails({ ...details, type })} options={types} /></FormField>
                {details.type === 'dine_in'
                  ? <FormField label={t('pos.sell.table')} error={errors.table_no}><Input dir="ltr" value={details.table_no} onChange={(event) => setDetails({ ...details, table_no: event.target.value })} /></FormField>
                  : <FormField label={t('pos.sell.customer')}><Input value={details.customer_name} onChange={(event) => setDetails({ ...details, customer_name: event.target.value })} /></FormField>}
                {canDiscount && <FormField label={t('pos.sell.discount')} error={errors.discount}><Input dir="ltr" inputMode="decimal" value={details.discount} onChange={(event) => setDetails({ ...details, discount: event.target.value })} /></FormField>}
              </div>
              {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
              <div className="flex items-baseline justify-between border-t pt-3">
                <span className="text-sm text-muted-foreground">{t('pos.sell.total')}</span>
                <span className="text-2xl font-semibold tabular-nums">{formatMoney(Math.max(0, total), locale)}</span>
              </div>
              <div className="grid grid-cols-[auto_1fr_2fr] gap-2">
                <Button variant="ghost" size="icon" disabled={cart.length === 0} onClick={() => setCart([])} aria-label={t('pos.sell.clear')}><Trash2 /></Button>
                <Button variant="outline" disabled={cart.length === 0 || sending} onClick={() => send(false)}>{t('pos.sell.hold')}</Button>
                <Button disabled={cart.length === 0 || sending} onClick={() => send(true)}>{t('pos.sell.pay')}</Button>
              </div>
            </CardContent>
          </Card>

          {openOrders.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="text-base">{t('pos.sell.held')}</CardTitle></CardHeader>
              <CardContent>
                <ul className="flex flex-col divide-y">
                  {openOrders.map((order) => (
                    <li key={order.id} className={cn('flex items-center gap-2 py-2 text-sm', order.channel === 'qr_menu' && 'bg-brass/5')}>
                      <span className="flex-1">
                        <span className="font-medium">#{order.number}</span>{' '}
                        <span className="text-xs text-muted-foreground">{order.table_no ? t('pos.orders.table', { table: order.table_no }) : order.customer_name ?? t(`pos.orders.channel.${order.channel}`)}</span>
                        <span className="block text-xs text-muted-foreground">{order.lines.map((line) => `${Number(line.quantity)}× ${line.name}`).join('، ')}</span>
                      </span>
                      <span className="tabular-nums">{formatMoney(order.total, locale)}</span>
                      <Button size="sm" onClick={() => setPayingId(order.id)}>{t('pos.sell.pay')}</Button>
                      {can('pos.orders.void') && (
                        <ConfirmAction href={url(`/pos/sell/${register.id}/orders/${order.id}/void`)} title={t('pos.orders.void')} variant="ghost" size="icon-sm">
                          <Ban />
                        </ConfirmAction>
                      )}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </aside>
      </div>

      <ModifierPicker product={picking} onClose={() => setPicking(null)} onAdd={add} />
      {paying && <PayDialog order={paying} registerId={register.id} methods={methods} staff={staff} onClose={() => setPayingId(null)} />}
    </SuiteLayout>
  );
}
