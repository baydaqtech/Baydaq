import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Coffee, Flame, Minus, Plus, ShoppingBag } from 'lucide-react';
import ModifierPicker from '@/components/pos/ModifierPicker';
import FormField from '@/components/platform/FormField';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import PlatformShell from '@/layouts/PlatformShell';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { cartLineUnitPrice, cartPayload, cartTotal, type CartLine, type MenuCategory, type MenuModifier, type MenuProduct } from '@/types/pos';

interface Props {
  token: string;
  place: { company: string | null; branch: string | null };
  categories: MenuCategory[];
  products: MenuProduct[];
}

/** The public QR menu: browse with SFDA labels, build an order and send it to the till. */
export default function Menu({ token, place, categories, products }: Props) {
  const t = useT();
  const { dir, locale } = useLocale();
  const [cart, setCart] = useState<CartLine[]>([]);
  const [picking, setPicking] = useState<MenuProduct | null>(null);
  const [open, setOpen] = useState(false);
  const [details, setDetails] = useState(() => ({ table_no: new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('table') ?? '', customer_name: '', customer_phone: '', note: '' }));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [sending, setSending] = useState(false);
  const count = cart.reduce((sum, line) => sum + line.quantity, 0);

  const sections = useMemo(() => {
    const grouped = categories.map((category) => ({ category, items: products.filter((product) => product.category_id === category.id) }));
    const rest = products.filter((product) => !categories.some((category) => category.id === product.category_id));
    return [...grouped, ...(rest.length ? [{ category: null, items: rest }] : [])].filter((section) => section.items.length > 0);
  }, [categories, products]);

  function add(product: MenuProduct, modifiers: MenuModifier[] = []) {
    const key = `${product.id}:${modifiers.map((modifier) => modifier.id).sort().join(',')}`;
    setCart((current) => (current.some((line) => line.key === key) ? current.map((line) => (line.key === key ? { ...line, quantity: line.quantity + 1 } : line)) : [...current, { key, product, modifiers, quantity: 1 }]));
    setPicking(null);
  }

  function setQuantity(key: string, quantity: number) {
    setCart((current) => (quantity <= 0 ? current.filter((line) => line.key !== key) : current.map((line) => (line.key === key ? { ...line, quantity } : line))));
  }

  function send() {
    setSending(true);
    router.post(`/menu/${token}/order`, { ...details, lines: cartPayload(cart) }, {
      preserveScroll: true,
      onError: (failed) => setErrors(failed),
      onSuccess: () => { setErrors({}); setCart([]); setOpen(false); },
      onFinish: () => setSending(false),
    });
  }

  return (
    <PlatformShell>
      <Head title={place.company ?? t('pos.menu.title')} />
      <div className="mx-auto flex min-h-svh max-w-2xl flex-col pb-24">
        <header className="sticky top-0 z-10 border-b bg-background/95 px-4 py-3 backdrop-blur">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-lg font-semibold">{place.company}</p>
              <p className="text-xs text-muted-foreground">{place.branch}</p>
            </div>
            <LocaleSwitcher className="" />
          </div>
          <nav className="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4">
            {sections.map(({ category }) => category && (
              <a key={category.id} href={`#category-${category.id}`} className="shrink-0 rounded-full border px-3 py-1 text-sm" style={category.color ? { borderColor: category.color } : undefined}>{category.name}</a>
            ))}
          </nav>
        </header>

        <main className="flex flex-col gap-6 px-4 py-4">
          {sections.map(({ category, items }) => (
            <section key={category?.id ?? 'other'} id={category ? `category-${category.id}` : undefined} className="scroll-mt-28">
              {category && <h2 className="mb-2 text-base font-semibold">{category.name}</h2>}
              <ul className="flex flex-col divide-y rounded-lg border bg-card">
                {items.map((product) => (
                  <li key={product.id} className="flex items-start gap-3 p-3">
                    <div className="flex-1">
                      <p className="font-medium">{product.name}</p>
                      {product.description && <p className="text-sm text-muted-foreground">{product.description}</p>}
                      <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        {product.calories !== null && <span className="flex items-center gap-1"><Flame className="size-3" />{product.calories.toLocaleString(locale)} {t('pos.sell.kcal')}{product.burn_minutes ? ` · ${t('pos.menu.burn', { minutes: product.burn_minutes.toLocaleString(locale) })}` : ''}</span>}
                        {product.salt_g && <span>{t('pos.menu.salt', { grams: Number(product.salt_g).toLocaleString(locale) })}</span>}
                        {product.caffeine_mg !== null && <span className="flex items-center gap-1"><Coffee className="size-3" />{t('pos.menu.caffeine', { mg: product.caffeine_mg.toLocaleString(locale) })}</span>}
                        {product.high_salt && <span className="rounded bg-amber-100 px-1.5 text-amber-800">🧂 {t('pos.menu.high_salt')}</span>}
                        {product.allergens.length > 0 && <span>{product.allergens.join('، ')}</span>}
                      </p>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                      <span className="text-sm font-semibold tabular-nums">{formatMoney(product.price, locale)}</span>
                      <Button size="icon-sm" variant="outline" onClick={() => (product.modifier_groups.length ? setPicking(product) : add(product))} aria-label={t('pos.sell.add')}><Plus /></Button>
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          ))}
          <p className="text-xs text-muted-foreground">{t('pos.menu.calories_note')}</p>
        </main>

        <Sheet open={open} onOpenChange={setOpen}>
          <SheetTrigger asChild>
            <Button size="lg" disabled={count === 0} className={cn('fixed inset-x-4 bottom-4 mx-auto max-w-2xl justify-between shadow-lg', count === 0 && 'opacity-0')}>
              <span className="flex items-center gap-2"><ShoppingBag />{t('pos.menu.items', { count })}</span>
              <span className="tabular-nums">{formatMoney(cartTotal(cart), locale)}</span>
            </Button>
          </SheetTrigger>
          <SheetContent side="bottom" dir={dir} className="max-h-[85svh] overflow-y-auto">
            <SheetHeader><SheetTitle>{t('pos.menu.order')}</SheetTitle></SheetHeader>
            <div className="flex flex-col gap-4 px-4 pb-6">
              {cart.length === 0 ? <p className="text-sm text-muted-foreground">{t('pos.menu.empty_cart')}</p> : (
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
              <div className="grid grid-cols-2 gap-3">
                <FormField label={t('pos.menu.table')} error={errors.table_no}><Input dir="ltr" inputMode="numeric" value={details.table_no} onChange={(event) => setDetails({ ...details, table_no: event.target.value })} /></FormField>
                <FormField label={t('pos.menu.name')} error={errors.customer_name}><Input value={details.customer_name} onChange={(event) => setDetails({ ...details, customer_name: event.target.value })} /></FormField>
                <FormField label={t('pos.menu.phone')} error={errors.customer_phone}><Input dir="ltr" type="tel" value={details.customer_phone} onChange={(event) => setDetails({ ...details, customer_phone: event.target.value })} /></FormField>
                <FormField label={t('pos.sell.note')} error={errors.note}><Input value={details.note} onChange={(event) => setDetails({ ...details, note: event.target.value })} /></FormField>
              </div>
              {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
              <Button size="lg" disabled={cart.length === 0 || sending} onClick={send} className="justify-between">
                <span>{t('pos.menu.send')}</span>
                <span className="tabular-nums">{formatMoney(cartTotal(cart), locale)}</span>
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </div>
      <ModifierPicker product={picking} onClose={() => setPicking(null)} onAdd={add} />
    </PlatformShell>
  );
}
