import { router, usePoll } from '@inertiajs/react';
import PageHeader from '@/components/platform/PageHeader';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { OrderResource } from '@/types/pos';

const COLUMNS = ['new', 'preparing', 'ready'] as const;
const NEXT: Record<string, string> = { new: 'preparing', preparing: 'ready', ready: 'served' };

function minutesSince(iso: string): number {
  return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
}

/** Kitchen display: today's orders by preparation stage, refreshed every ten seconds. */
export default function Kitchen({ orders }: { orders: OrderResource[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  usePoll(10000, { only: ['orders'] }, { keepAlive: true });

  function advance(order: OrderResource) {
    router.put(url(`/pos/orders/${order.id}/kitchen`), { kitchen_status: NEXT[order.kitchen_status] }, { preserveScroll: true, only: ['orders'] });
  }

  return (
    <SuiteLayout title={t('pos.orders.kitchen')}>
      <PageHeader title={t('pos.orders.kitchen')} />
      {orders.length === 0 ? <StatusMessage>{t('pos.orders.kitchen_empty')}</StatusMessage> : (
        <div className="grid gap-4 md:grid-cols-3">
          {COLUMNS.map((column) => (
            <section key={column} className="flex flex-col gap-3">
              <h2 className="text-sm font-semibold text-muted-foreground">{t(`pos.kitchen_status.${column}`)} ({orders.filter((order) => order.kitchen_status === column).length})</h2>
              {orders.filter((order) => order.kitchen_status === column).map((order) => {
                const waited = minutesSince(order.created_at);
                return (
                  <Card key={order.id} className={cn(column === 'new' && waited >= 15 && 'border-red-300')}>
                    <CardHeader>
                      <CardTitle className="flex items-center justify-between text-base">
                        <span>#{order.number} <span className="text-xs font-normal text-muted-foreground">{order.table_no ? t('pos.orders.table', { table: order.table_no }) : t(`pos.order_type.${order.type}`)}</span></span>
                        <span className="text-xs font-normal tabular-nums text-muted-foreground">{waited.toLocaleString(locale)}′</span>
                      </CardTitle>
                    </CardHeader>
                    <CardContent>
                      <ul className="flex flex-col gap-1 text-sm">
                        {order.lines.map((line) => (
                          <li key={line.id}>
                            <span className="font-medium tabular-nums">{Number(line.quantity)}×</span> {line.name}
                            {line.note && <span className="block text-xs text-amber-700">{line.note}</span>}
                          </li>
                        ))}
                      </ul>
                      {order.note && <p className="mt-2 text-xs text-amber-700">{order.note}</p>}
                      {order.branch && <p className="mt-2 text-xs text-muted-foreground">{order.branch}</p>}
                    </CardContent>
                    <CardFooter><Button size="sm" className="w-full" onClick={() => advance(order)}>{t(`pos.orders.advance.${column}`)}</Button></CardFooter>
                  </Card>
                );
              })}
            </section>
          ))}
        </div>
      )}
    </SuiteLayout>
  );
}
