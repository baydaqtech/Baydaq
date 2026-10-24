import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck, Clock } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PlatformShell from '@/layouts/PlatformShell';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Slot } from '@/types/reservations';

interface PublicService { id: number; name: string; description: string | null; duration_minutes: number; price: string; deposit: string }

interface Props {
  token: string;
  place: { company: string; branch: string };
  services: PublicService[];
  window: { from: string; to: string };
  slots: Slot[] | null;
}

/** A branch's public booking page: service, day, time, then name and mobile. */
export default function Book({ token, place, services, window: range, slots }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const flash = usePage().flash as { booking?: { reference: string; status: string; deposit: string } };
  const [done, setDone] = useState(flash.booking ?? null);
  const form = useForm({ service_id: 0, date: range.from, starts_at: '', party_size: 1, customer_name: '', customer_phone: '', customer_email: '', note: '' });
  const service = services.find((item) => item.id === form.data.service_id);

  function load(next: { service_id?: number; date?: string }) {
    const data = { service_id: next.service_id ?? form.data.service_id, date: next.date ?? form.data.date };
    form.setData((current) => ({ ...current, ...data, starts_at: '' }));
    router.reload({ only: ['slots'], data });
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(`/book/${token}`, {
      preserveScroll: true,
      onSuccess: (page) => { setDone((page.flash as typeof flash).booking ?? null); form.reset(); },
    });
  }

  return (
    <PlatformShell>
      <Head title={`${t('reservations.book.title')} · ${place.company}`} />
      <div className="mx-auto flex min-h-svh max-w-xl flex-col gap-4 px-4 py-4">
        <header className="flex items-center justify-between">
          <div>
            <p className="text-lg font-semibold">{place.company}</p>
            <p className="text-xs text-muted-foreground">{place.branch}</p>
          </div>
          <LocaleSwitcher className="" />
        </header>

        {done ? (
          <Card>
            <CardContent className="flex flex-col items-center gap-3 py-8 text-center">
              <CalendarCheck className="size-10 text-emerald-600" />
              <p className="text-lg font-semibold">{t('reservations.book.done', { reference: done.reference })}</p>
              {done.status === 'pending' && <p className="text-sm text-muted-foreground">{t('reservations.book.pending', { amount: done.deposit })}</p>}
              <Button variant="outline" onClick={() => setDone(null)}>{t('reservations.book.another')}</Button>
            </CardContent>
          </Card>
        ) : (
          <form onSubmit={submit} className="flex flex-col gap-4">
            <h1 className="text-base font-semibold">{t('reservations.book.service')}</h1>
            <div className="flex flex-col gap-2">
              {services.map((item) => (
                <button key={item.id} type="button" onClick={() => load({ service_id: item.id })} className={cn('flex items-start justify-between gap-3 rounded-lg border bg-card p-3 text-start', form.data.service_id === item.id && 'border-brass ring-1 ring-brass')}>
                  <span>
                    <span className="block font-medium">{item.name}</span>
                    {item.description && <span className="block text-sm text-muted-foreground">{item.description}</span>}
                    <span className="mt-1 flex items-center gap-1 text-xs text-muted-foreground"><Clock className="size-3" />{t('reservations.book.minutes', { count: item.duration_minutes })}</span>
                  </span>
                  <span className="text-sm font-semibold tabular-nums">{formatMoney(item.price, locale)}</span>
                </button>
              ))}
            </div>

            {service && (
              <>
                <FormField label={t('reservations.book.date')}>
                  <Input type="date" dir="ltr" min={range.from} max={range.to} value={form.data.date} onChange={(event) => event.target.value && load({ date: event.target.value })} />
                </FormField>
                <FormField label={t('reservations.book.time')} error={form.errors.starts_at}>
                  {slots && slots.length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                      {slots.map((slot) => (
                        <Button key={slot.starts_at} type="button" size="sm" variant={form.data.starts_at === slot.starts_at ? 'default' : 'outline'} onClick={() => form.setData('starts_at', slot.starts_at)}>
                          <span dir="ltr">{slot.time}</span>
                        </Button>
                      ))}
                    </div>
                  ) : <p className="text-sm text-muted-foreground">{t('reservations.book.no_slots')}</p>}
                </FormField>
              </>
            )}

            {form.data.starts_at && (
              <div className="grid gap-3 sm:grid-cols-2">
                <FormField label={t('reservations.book.name')} error={form.errors.customer_name}><Input autoComplete="name" value={form.data.customer_name} onChange={(event) => form.setData('customer_name', event.target.value)} /></FormField>
                <FormField label={t('reservations.book.phone')} error={form.errors.customer_phone}><Input dir="ltr" type="tel" autoComplete="tel" value={form.data.customer_phone} onChange={(event) => form.setData('customer_phone', event.target.value)} /></FormField>
                <FormField label={t('reservations.book.email')} error={form.errors.customer_email}><Input dir="ltr" type="email" autoComplete="email" value={form.data.customer_email} onChange={(event) => form.setData('customer_email', event.target.value)} /></FormField>
                <FormField label={t('reservations.book.note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
                {service && Number(service.deposit) > 0 && <p className="text-sm text-muted-foreground sm:col-span-2">{t('reservations.book.pending', { amount: service.deposit })}</p>}
                <Button type="submit" size="lg" className="sm:col-span-2" disabled={form.processing}>{t('reservations.book.submit')}</Button>
              </div>
            )}
          </form>
        )}
      </div>
    </PlatformShell>
  );
}
