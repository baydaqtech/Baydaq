import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { BookingResource, Slot } from '@/types/reservations';
import type { Option } from '@/types/resources';

interface ServiceOption extends Option { duration_minutes: number; price: string; color: string | null }

interface Props {
  branches: Option[];
  branch_id: number | null;
  date: string;
  resources: { id: number; name: string; kind: string; capacity: number }[];
  services: ServiceOption[];
  staff: Option[];
  bookings: BookingResource[];
  slots: Slot[] | null;
  statuses: Option[];
  payment_means: Option[];
}

function shiftDate(date: string, days: number): string {
  const value = new Date(`${date}T12:00:00Z`);
  value.setUTCDate(value.getUTCDate() + days);
  return value.toISOString().slice(0, 10);
}

function NewBooking({ branchId, date, services, staff, slots }: { branchId: number; date: string; services: ServiceOption[]; staff: Option[]; slots: Slot[] | null }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ branch_id: branchId, service_id: '' as number | '', starts_at: '', party_size: 1, customer_name: '', customer_phone: '', note: '', employee_id: '' as number | '' });
  const f = (field: string) => t(`reservations.calendar.fields.${field}`);
  const chosen = slots?.find((slot) => slot.starts_at === form.data.starts_at);

  function loadSlots(serviceId: number, partySize = form.data.party_size) {
    form.setData((data) => ({ ...data, service_id: serviceId, starts_at: '', employee_id: '' }));
    router.reload({ only: ['slots'], data: { slot_service: serviceId, party_size: partySize } });
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, employee_id: data.employee_id || null }));
    form.post(url('/reservations/bookings'), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Plus data-icon="inline-start" />{t('reservations.calendar.new')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-w-xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('reservations.calendar.new')}</DialogTitle><DialogDescription>{date}</DialogDescription></DialogHeader>
          <FormField className="sm:col-span-2" label={f('service')} error={form.errors.service_id}><SelectInput value={form.data.service_id} onChange={(value) => loadSlots(Number(value))} options={services} /></FormField>
          <FormField className="sm:col-span-2" label={t('reservations.calendar.pick_slot')} error={form.errors.starts_at}>
            {form.data.service_id === '' ? null : slots && slots.length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {slots.map((slot) => (
                  <Button key={slot.starts_at} type="button" size="xs" variant={form.data.starts_at === slot.starts_at ? 'default' : 'outline'} onClick={() => form.setData((data) => ({ ...data, starts_at: slot.starts_at, employee_id: '' }))}>
                    <span dir="ltr">{slot.time}</span>
                  </Button>
                ))}
              </div>
            ) : <p className="text-sm text-muted-foreground">{t('reservations.calendar.no_slots')}</p>}
          </FormField>
          {chosen && (chosen.employee_ids?.length ?? 0) > 1 && (
            <FormField className="sm:col-span-2" label={f('employee')} error={form.errors.employee_id}>
              <SelectInput value={form.data.employee_id} onChange={(value) => form.setData('employee_id', value === '' ? '' : Number(value))} options={staff.filter((item) => chosen.employee_ids!.includes(Number(item.value)))} emptyLabel="—" />
            </FormField>
          )}
          <FormField label={f('customer_name')} error={form.errors.customer_name}><Input value={form.data.customer_name} onChange={(event) => form.setData('customer_name', event.target.value)} /></FormField>
          <FormField label={f('customer_phone')} error={form.errors.customer_phone}><Input dir="ltr" type="tel" value={form.data.customer_phone} onChange={(event) => form.setData('customer_phone', event.target.value)} /></FormField>
          <FormField label={f('party_size')} error={form.errors.party_size}>
            <Input dir="ltr" type="number" min={1} value={form.data.party_size} onChange={(event) => { const size = Math.max(1, Number(event.target.value)); form.setData('party_size', size); if (form.data.service_id !== '') { loadSlots(Number(form.data.service_id), size); } }} />
          </FormField>
          <FormField label={f('note')} error={form.errors.note}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing || !form.data.starts_at}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function BookingDialog({ booking, paymentMeans, onClose }: { booking: BookingResource; paymentMeans: Option[]; onClose: () => void }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [reason, setReason] = useState('');
  const [means, setMeans] = useState(String(paymentMeans[0]?.value ?? '10'));
  const f = (field: string) => t(`reservations.calendar.fields.${field}`);
  const options = { preserveScroll: true, onSuccess: onClose };

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent dir={dir} className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">{booking.customer_name} <StatusBadge file="reservations" group="booking_status" value={booking.status} /></DialogTitle>
          <DialogDescription><span dir="ltr">{booking.start}–{booking.end}</span> · {booking.service} · {booking.reference}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-2 gap-2 text-sm">
          <dt className="text-muted-foreground">{f('customer_phone')}</dt><dd dir="ltr" className="text-end">{booking.customer_phone}</dd>
          {booking.resource && <><dt className="text-muted-foreground">{f('resource')}</dt><dd className="text-end">{booking.resource}</dd></>}
          {booking.employee && <><dt className="text-muted-foreground">{f('employee')}</dt><dd className="text-end">{booking.employee}</dd></>}
          <dt className="text-muted-foreground">{f('price')}</dt><dd className="text-end tabular-nums">{formatMoney(booking.price, locale)}</dd>
          {Number(booking.deposit) > 0 && <><dt className="text-muted-foreground">{t('reservations.calendar.deposit_due', { amount: '' })}</dt><dd className="text-end tabular-nums">{formatMoney(booking.deposit, locale)}{booking.deposit_paid ? ' ✓' : ''}</dd></>}
          {booking.note && <><dt className="text-muted-foreground">{f('note')}</dt><dd className="text-end">{booking.note}</dd></>}
        </dl>
        {booking.next.includes('cancelled') && can('reservations.bookings.cancel') && (
          <FormField label={t('reservations.calendar.reason')}><Input value={reason} onChange={(event) => setReason(event.target.value)} /></FormField>
        )}
        {['confirmed', 'checked_in'].includes(booking.status) && can('reservations.bookings.checkout') && (
          <FormField label={f('payment_means')}><SelectInput value={means} onChange={setMeans} options={paymentMeans} /></FormField>
        )}
        <DialogFooter className="flex-wrap gap-2">
          {Number(booking.deposit) > 0 && !booking.deposit_paid && can('reservations.bookings.update') && (
            <Button variant="outline" onClick={() => router.put(url(`/reservations/bookings/${booking.id}/deposit`), {}, options)}>{t('reservations.calendar.deposit_received')}</Button>
          )}
          {booking.next.filter((status) => status !== 'cancelled' && !(status === 'confirmed' && Number(booking.deposit) > 0 && !booking.deposit_paid)).map((status) => can('reservations.bookings.update') && (
            <Button key={status} variant="outline" onClick={() => router.put(url(`/reservations/bookings/${booking.id}/status`), { status }, options)}>{t(`reservations.calendar.actions.${status}`)}</Button>
          ))}
          {booking.next.includes('cancelled') && can('reservations.bookings.cancel') && (
            <Button variant="destructive" onClick={() => router.put(url(`/reservations/bookings/${booking.id}/status`), { status: 'cancelled', reason }, options)}>{t('reservations.calendar.actions.cancelled')}</Button>
          )}
          {['confirmed', 'checked_in'].includes(booking.status) && can('reservations.bookings.checkout') && (
            <Button onClick={() => router.post(url(`/reservations/bookings/${booking.id}/checkout`), { payment_means: means }, options)}>{t('reservations.calendar.checkout')}</Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default function Calendar({ branches, branch_id: branchId, date, resources, services, staff, bookings, slots, payment_means: paymentMeans }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const [openId, setOpenId] = useState<number | null>(null);
  const open = bookings.find((booking) => booking.id === openId) ?? null;
  const columns = [...resources.map((resource) => ({ id: resource.id as number | null, name: resource.name })), ...(bookings.some((booking) => booking.resource_id === null) ? [{ id: null, name: t('reservations.calendar.unassigned') }] : [])];

  function visit(next: { branch?: number; date?: string }) {
    router.get(window.location.pathname, { branch: next.branch ?? branchId, date: next.date ?? date }, { preserveScroll: true, preserveState: false });
  }

  if (branchId === null) {
    return <SuiteLayout title={t('reservations.calendar.title')}><StatusMessage>{t('reservations.calendar.no_branch')}</StatusMessage></SuiteLayout>;
  }

  return (
    <SuiteLayout title={t('reservations.calendar.title')}>
      <PageHeader title={t('reservations.calendar.title')} actions={can('reservations.bookings.create') && services.length > 0 && <NewBooking branchId={branchId} date={date} services={services} staff={staff} slots={slots} />}>
        <div className="flex flex-wrap items-center gap-2">
          {branches.length > 1 && <SelectInput className="w-48" value={branchId} onChange={(value) => visit({ branch: Number(value) })} options={branches} />}
          <Button size="icon-sm" variant="outline" onClick={() => visit({ date: shiftDate(date, -1) })} aria-label="-1"><ChevronRight className="ltr:rotate-180" /></Button>
          <Input type="date" dir="ltr" className="w-40" value={date} onChange={(event) => event.target.value && visit({ date: event.target.value })} />
          <Button size="icon-sm" variant="outline" onClick={() => visit({ date: shiftDate(date, 1) })} aria-label="+1"><ChevronLeft className="ltr:rotate-180" /></Button>
          <span className="text-sm text-muted-foreground">{new Date(`${date}T12:00:00Z`).toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' })}</span>
        </div>
      </PageHeader>

      {bookings.length === 0 && <StatusMessage className="mb-4">{t('reservations.calendar.empty')}</StatusMessage>}
      <div className="overflow-x-auto pb-2">
      <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${Math.max(1, columns.length)}, minmax(12rem, 1fr))` }}>
        {columns.map((column) => (
          <section key={column.id ?? 'none'} className="flex min-w-0 flex-col gap-2">
            <h2 className="text-sm font-semibold text-muted-foreground">{column.name}</h2>
            {bookings.filter((booking) => booking.resource_id === column.id).map((booking) => (
              <button
                key={booking.id}
                type="button"
                onClick={() => setOpenId(booking.id)}
                className={cn('flex flex-col gap-0.5 rounded-md border border-s-4 bg-card p-2 text-start text-sm transition hover:shadow-sm', ['cancelled', 'no_show'].includes(booking.status) && 'opacity-50 line-through')}
                style={{ borderInlineStartColor: booking.color ?? '#c8a84b' }}
              >
                <span className="flex items-center justify-between gap-2"><span dir="ltr" className="font-medium tabular-nums">{booking.start}–{booking.end}</span><StatusBadge file="reservations" group="booking_status" value={booking.status} /></span>
                <span>{booking.customer_name}{booking.party_size > 1 ? ` · ${booking.party_size}` : ''}</span>
                <span className="text-xs text-muted-foreground">{booking.service}{booking.employee ? ` · ${booking.employee}` : ''}</span>
              </button>
            ))}
          </section>
        ))}
      </div>
      </div>

      {open && <BookingDialog booking={open} paymentMeans={paymentMeans} onClose={() => setOpenId(null)} />}
    </SuiteLayout>
  );
}
