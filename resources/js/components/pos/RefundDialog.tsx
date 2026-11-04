import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Minus, Plus, Undo2 } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { OrderResource } from '@/types/pos';
import type { Option } from '@/types/resources';

/** How many of a line can still come back. */
function left(line: OrderResource['lines'][number]): number {
  return Math.max(0, Number(line.quantity) - Number(line.returned));
}

/**
 * Refund some or all of a paid order: a stepper per line (up to what can still come back), how
 * the money goes back, and the reason for the credit note. The total shown is what the server
 * will refund: each line at what the customer paid for it.
 */
export default function RefundDialog({ order, methods }: { order: OrderResource; methods: Option[] }) {
  const t = useT();
  const { locale, dir } = useLocale();
  const url = useSuiteUrl();
  const r = (key: string, replace?: Record<string, string>) => t(`pos.refunds.${key}`, replace);
  const [open, setOpen] = useState(false);
  const available = methods.filter((method) => method.value !== 'wallet' || order.has_customer);
  const form = useForm<{ lines: Record<number, number>; method: string; reason: string }>({ lines: {}, method: String(available[0]?.value ?? 'cash'), reason: '' });

  const total = useMemo(() => order.lines.reduce((sum, line) => sum + (Number(line.paid) * (form.data.lines[line.id] ?? 0)) / Number(line.quantity), 0), [order.lines, form.data.lines]);
  const set = (lineId: number, quantity: number, max: number) => form.setData('lines', { ...form.data.lines, [lineId]: Math.min(max, Math.max(0, quantity)) });

  return (
    <Dialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) form.reset(); }}>
      <DialogTrigger asChild>
        <Button size="sm" variant="ghost"><Undo2 data-icon="inline-start" />{r('action')}</Button>
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{r('title', { number: String(order.number) })}</DialogTitle>
          <DialogDescription>{r('lede')}</DialogDescription>
        </DialogHeader>
        <form
          id={`refund-${order.id}`}
          className="flex flex-col gap-4"
          onSubmit={(event) => { event.preventDefault(); form.post(url(`/pos/orders/${order.id}/refunds`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }}
        >
          <ul className="divide-y rounded-lg border">
            {order.lines.map((line) => {
              const max = left(line);
              const quantity = form.data.lines[line.id] ?? 0;

              return (
                <li key={line.id} className={cn('flex items-center justify-between gap-3 px-3 py-2 text-sm', max === 0 && 'opacity-50')}>
                  <span className="min-w-0">
                    <span className="block truncate font-medium">{line.name}</span>
                    <span className="text-xs text-muted-foreground">{formatMoney(Number(line.paid) / Number(line.quantity), locale)} · {r('of', { quantity: formatNumber(max, locale) })}</span>
                  </span>
                  <span className="flex items-center gap-1" aria-label={r('quantity')}>
                    <Button type="button" size="icon-xs" variant="outline" disabled={quantity <= 0} onClick={() => set(line.id, quantity - 1, max)} aria-label="−"><Minus /></Button>
                    <span className="w-8 text-center tabular-nums">{formatNumber(quantity, locale)}</span>
                    <Button type="button" size="icon-xs" variant="outline" disabled={quantity >= max} onClick={() => set(line.id, quantity + 1, max)} aria-label="+"><Plus /></Button>
                  </span>
                </li>
              );
            })}
          </ul>
          {(form.errors as Record<string, string>).lines && <p className="text-sm text-destructive">{(form.errors as Record<string, string>).lines}</p>}

          <FormField label={r('method')} error={form.errors.method}>
            <div className="flex flex-wrap gap-1" role="radiogroup">
              {available.map((method) => (
                <Button key={method.value} type="button" size="sm" role="radio" aria-checked={form.data.method === method.value} variant={form.data.method === method.value ? 'secondary' : 'ghost'} onClick={() => form.setData('method', String(method.value))}>
                  {method.label}
                </Button>
              ))}
            </div>
          </FormField>
          <FormField label={r('reason')} hint={r('reason_hint')} error={form.errors.reason}>
            <Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
          </FormField>
        </form>
        <DialogFooter className="items-center justify-between gap-2 sm:justify-between">
          <span className="text-sm">{r('total')}: <span className="font-semibold tabular-nums">{formatMoney(total.toFixed(2), locale)}</span></span>
          <Button type="submit" form={`refund-${order.id}`} disabled={form.processing || total <= 0}>{r('submit', { amount: formatMoney(total.toFixed(2), locale) })}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
