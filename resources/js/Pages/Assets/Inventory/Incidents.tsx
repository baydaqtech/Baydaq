import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { PhotoGrid } from '@/components/assets/InventoryWidgets';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import Paginator from '@/components/platform/Paginator';
import SelectInput from '@/components/platform/SelectInput';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import StatusMessage from '@/components/platform/StatusMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { IncidentSummary } from '@/types/inventory';
import type { LaravelPage, Option } from '@/types/resources';

interface Props {
  incidents: LaravelPage<IncidentSummary>;
  totals: { count: number; value: string };
  by_kind: { kind: string; count: number; value: string }[];
  filters: Filters;
  kinds: Option[];
  resolutions: Option[];
  statuses: Option[];
  warehouses: Option[];
  can: { resolve: boolean; charge: boolean };
}

function ResolveDialog({ incident, resolutions, warehouses, canCharge }: { incident: IncidentSummary; resolutions: Option[]; warehouses: Option[]; canCharge: boolean }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.incidents.fields.${field}`);
  const options = resolutions.filter((option) => option.value !== 'charged' || (canCharge && incident.responsible_employee_id));
  const form = useForm({ resolution: String(options[0]?.value ?? 'written_off'), note: '', charge_amount: incident.value, warehouse_id: warehouses[0]?.value ?? '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, note: data.note || null, charge_amount: data.resolution === 'charged' ? data.charge_amount : null, warehouse_id: data.resolution === 'recovered' && !incident.is_asset ? data.warehouse_id : null }));
    form.put(url(`/assets/inventory/incidents/${incident.id}/resolve`), { preserveScroll: true, onSuccess: () => setOpen(false) });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline">{t('assets.incidents.resolve')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>{incident.number} · {incident.label}</DialogTitle>
            <DialogDescription>{t(`assets.incident_kind.${incident.kind}`)} · {formatNumber(incident.quantity, locale)} {incident.unit ?? ''} · {formatMoney(incident.value, locale)}{incident.responsible ? ` · ${incident.responsible}` : ''}</DialogDescription>
          </DialogHeader>
          <FormField label={f('resolution')} error={form.errors.resolution}><SelectInput value={form.data.resolution} onChange={(value) => form.setData('resolution', value)} options={options} /></FormField>
          {form.data.resolution === 'charged' && <FormField label={f('charge_amount')} hint={t('assets.incidents.charge_hint')} error={form.errors.charge_amount}><Input dir="ltr" inputMode="decimal" value={form.data.charge_amount} onChange={(event) => form.setData('charge_amount', event.target.value)} /></FormField>}
          {form.data.resolution === 'recovered' && !incident.is_asset && <FormField label={f('warehouse')} error={form.errors.warehouse_id}><SelectInput value={form.data.warehouse_id} onChange={(value) => form.setData('warehouse_id', Number(value))} options={warehouses} /></FormField>}
          <FormField label={f('note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('assets.incidents.resolve')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** Incidents as cards: what, how many, worth, who, where, the photos, and how it was settled. */
export function IncidentList({ incidents, actions }: { incidents: IncidentSummary[]; actions?: (incident: IncidentSummary) => React.ReactNode }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <div className="flex flex-col gap-3">
      {incidents.map((incident) => (
        <Card key={incident.id}>
          <CardContent className="flex flex-col gap-2 py-4 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="flex flex-wrap items-center gap-2 font-medium"><AlertTriangle className="size-4 text-amber-600" />{t(`assets.incident_kind.${incident.kind}`)} · {formatNumber(incident.quantity, locale)} {incident.unit ?? ''} {incident.label}</p>
                <p className="text-xs text-muted-foreground"><span dir="ltr" className="font-mono">{incident.number}</span> · {formatDate(incident.created_at, locale)}{incident.holder ? ` · ${incident.holder}` : ''}{incident.responsible ? ` · ${t('assets.incidents.fields.responsible')}: ${incident.responsible}` : ''}</p>
                {incident.description && <p className="mt-1">{incident.description}</p>}
              </div>
              <div className="flex items-center gap-2">
                <span className="font-semibold tabular-nums">{formatMoney(incident.value, locale)}</span>
                <StatusBadge file="assets" group="incident_status" value={incident.status} />
                {actions?.(incident)}
              </div>
            </div>
            {incident.resolution && <p className="text-xs text-emerald-700">{t(`assets.incident_resolution.${incident.resolution}`)}{incident.charged_amount ? ` · ${formatMoney(incident.charged_amount, locale)}` : ''}{incident.resolution_note ? ` · ${incident.resolution_note}` : ''}</p>}
            <PhotoGrid photos={incident.photos} />
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

export default function Incidents({ incidents, totals, by_kind: byKind, filters: initialFilters, kinds, resolutions, statuses, warehouses, can }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`assets.incidents.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.incidents.title')}>
      <PageHeader title={t('assets.incidents.title')} description={t('assets.incidents.total', { count: formatNumber(totals.count, locale), value: formatMoney(totals.value, locale) })} />
      {byKind.length > 0 && (
        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {byKind.map((row) => <StatCard key={row.kind} label={t(`assets.incident_kind.${row.kind}`)} value={`${formatNumber(row.count, locale)} · ${formatMoney(row.value, locale)}`} icon={AlertTriangle} />)}
        </div>
      )}
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: f('status'), options: statuses }, { key: 'kind', label: f('kind'), options: kinds }]} />
      {incidents.data.length === 0 ? <StatusMessage>{t('assets.incidents.none')}</StatusMessage> : (
        <IncidentList incidents={incidents.data} actions={(incident) => can.resolve && incident.status === 'open' && <ResolveDialog incident={incident} resolutions={resolutions} warehouses={warehouses} canCharge={can.charge} />} />
      )}
      <Paginator page={incidents} />
    </SuiteLayout>
  );
}
