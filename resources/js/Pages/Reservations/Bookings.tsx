import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { BookingResource } from '@/types/reservations';
import type { Option, Paginated } from '@/types/resources';

export default function Bookings({ bookings, filters: initialFilters, statuses }: { bookings: Paginated<BookingResource>; filters: Filters; statuses: Option[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`reservations.calendar.fields.${field}`);

  return (
    <SuiteLayout title={t('suite.module_nav.reservations.bookings')}>
      <PageHeader title={t('suite.module_nav.reservations.bookings')} />
      <div className="flex flex-wrap items-start gap-2">
        <Input type="date" dir="ltr" className="w-40" aria-label={f('from')} value={String(filters.from ?? '')} onChange={(event) => setFilter('from', event.target.value)} />
        <Input type="date" dir="ltr" className="w-40" aria-label={f('to')} value={String(filters.to ?? '')} onChange={(event) => setFilter('to', event.target.value)} />
        <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'status', label: f('status'), options: statuses }]} />
      </div>
      <DataTable<BookingResource>
        rows={bookings}
        rowHref={(booking) => `${url('/reservations/calendar')}${url('/reservations/calendar').includes('?') ? '&' : '?'}date=${booking.date}&branch=${booking.branch_id}`}
        empty={t('reservations.calendar.empty')}
        columns={[
          { key: 'reference', label: f('reference'), render: (booking) => <span dir="ltr" className="font-mono text-xs">{booking.reference}</span> },
          { key: 'date', label: f('date'), render: (booking) => <span dir="ltr" className="tabular-nums">{booking.date} {booking.start}</span> },
          { key: 'customer', label: f('customer_name'), render: (booking) => <span><span className="block">{booking.customer_name}</span><span dir="ltr" className="text-xs text-muted-foreground">{booking.customer_phone}</span></span> },
          { key: 'service', label: f('service'), render: (booking) => <span>{booking.service}<span className="block text-xs text-muted-foreground">{[booking.branch, booking.resource, booking.employee].filter(Boolean).join(' · ')}</span></span> },
          { key: 'price', label: f('price'), render: (booking) => <span className="tabular-nums">{formatMoney(booking.price, locale)}</span> },
          { key: 'status', label: f('status'), render: (booking) => <span className="flex flex-col items-start gap-1"><StatusBadge file="reservations" group="booking_status" value={booking.status} /><span className="text-xs text-muted-foreground">{t(`reservations.booking_channel.${booking.channel}`)}</span></span> },
        ]}
      />
    </SuiteLayout>
  );
}
