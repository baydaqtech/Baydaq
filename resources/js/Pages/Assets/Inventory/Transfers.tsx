import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { LinesEditor, linesPayload } from '@/components/assets/InventoryWidgets';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { CatalogVariant, DraftLine, MovableAsset, StockMap } from '@/types/inventory';
import type { Option, Paginated } from '@/types/resources';

interface TransferRow { id: number; number: string; status: string; lines: number; from: string; to: string; created_at: string; sent_at: string | null; received_at: string | null; [key: string]: unknown }
interface Props { transfers: Paginated<TransferRow>; filters: Filters; statuses: Option[]; sources: Option[]; destinations: Option[]; catalog: CatalogVariant[]; stock: StockMap; assets: MovableAsset[] }

function NewTransfer({ sources, destinations, catalog, stock, assets }: Omit<Props, 'transfers' | 'filters' | 'statuses'>) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const [lines, setLines] = useState<DraftLine[]>([]);
  const form = useForm({ from_warehouse_id: sources[0]?.value ?? '', to_warehouse_id: '' as number | '', note: '' });
  const errors = form.errors as Record<string, string>;
  const f = (field: string) => t(`assets.transfers.fields.${field}`);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, note: data.note || null, lines: linesPayload(lines) }));
    form.post(url('/assets/inventory/transfers'));
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><ArrowLeftRight data-icon="inline-start" />{t('assets.transfers.new')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-3xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.transfers.new')}</DialogTitle></DialogHeader>
          <FormField label={f('from')} error={errors.from_warehouse_id}><SelectInput value={form.data.from_warehouse_id} onChange={(value) => { form.setData('from_warehouse_id', Number(value)); setLines([]); }} options={sources} /></FormField>
          <FormField label={f('to')} error={errors.to_warehouse_id}><SelectInput value={form.data.to_warehouse_id} onChange={(value) => form.setData('to_warehouse_id', Number(value))} options={destinations.filter((option) => option.value !== form.data.from_warehouse_id)} /></FormField>
          <div className="sm:col-span-2">
            <LinesEditor lines={lines} onChange={setLines} catalog={catalog} assets={assets} stock={stock} holder={`warehouse:${form.data.from_warehouse_id}`} error={errors.lines ?? Object.entries(errors).find(([key]) => key.startsWith('lines.') || key === 'quantity')?.[1]} />
          </div>
          <FormField className="sm:col-span-2" label={f('note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing || lines.length === 0 || !form.data.to_warehouse_id}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Transfers({ transfers, filters: initialFilters, statuses, ...rest }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`assets.transfers.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.transfers.title')}>
      <PageHeader title={t('assets.transfers.title')} actions={can('assets.transfers.create') && rest.sources.length > 0 && <NewTransfer {...rest} />} />
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: f('status'), options: statuses }]} />
      <DataTable<TransferRow>
        rows={transfers}
        rowHref={(row) => url(`/assets/inventory/transfers/${row.id}`)}
        empty={t('assets.transfers.none')}
        columns={[
          { key: 'number', label: f('number'), render: (row) => <span dir="ltr" className="font-mono text-xs">{row.number}</span> },
          { key: 'route', label: `${f('from')} → ${f('to')}`, render: (row) => <span>{row.from} → {row.to}</span> },
          { key: 'lines', label: f('lines'), render: (row) => row.lines },
          { key: 'date', label: f('date'), render: (row) => formatDate(row.received_at ?? row.sent_at ?? row.created_at, locale) },
          { key: 'status', label: f('status'), render: (row) => <StatusBadge file="assets" group="transfer_status" value={row.status} /> },
        ]}
      />
    </SuiteLayout>
  );
}
