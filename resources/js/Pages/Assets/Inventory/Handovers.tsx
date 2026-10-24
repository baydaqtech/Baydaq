import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Handshake, Plus, Trash2 } from 'lucide-react';
import { LinesEditor, PhotoInput, linesPayload } from '@/components/assets/InventoryWidgets';
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

export interface HandoverSummary {
  id: number; number: string; status: string; purpose: string | null; note: string | null; channel: string;
  from: { type: string; id: number; name: string }; to: { type: string; id: number; name: string }; lines: number | null; created_at: string; accepted_at: string | null;
  [key: string]: unknown;
}
interface HolderOption extends Option { company_id: number }
interface WriteOff { variant_id: number | ''; kind: string; quantity: string; description: string }
interface Props { handovers: Paginated<HandoverSummary>; filters: Filters; statuses: Option[]; warehouses: HolderOption[]; people: HolderOption[]; catalog: CatalogVariant[]; stock: StockMap; assets: MovableAsset[]; kinds: Option[] }

function NewHandover({ warehouses, people, catalog, stock, assets, kinds }: Omit<Props, 'handovers' | 'filters' | 'statuses'>) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const [lines, setLines] = useState<DraftLine[]>([]);
  const [writeOffs, setWriteOffs] = useState<WriteOff[]>([]);
  const holders = [...warehouses, ...people];
  const form = useForm<{ from: string; to: string; purpose: string; note: string; photos: File[] }>({ from: String(warehouses[0]?.value ?? people[0]?.value ?? ''), to: '', purpose: '', note: '', photos: [] });
  const errors = form.errors as Record<string, string>;
  const f = (field: string) => t(`assets.handovers.fields.${field}`);
  const fromCompany = holders.find((holder) => holder.value === form.data.from)?.company_id;
  const fromEmployee = form.data.from.startsWith('employee:');
  const held = catalog.filter((variant) => Number(stock[form.data.from]?.[variant.id] ?? 0) > 0);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      ...data, purpose: data.purpose || null, note: data.note || null, lines: linesPayload(lines),
      write_offs: writeOffs.filter((row) => row.variant_id && Number(row.quantity) > 0).map((row) => ({ ...row, description: row.description || null })),
    }));
    form.post(url('/assets/inventory/handovers'));
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Handshake data-icon="inline-start" />{t('assets.handovers.new')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-3xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.handovers.new')}</DialogTitle></DialogHeader>
          <FormField label={f('from')} error={errors.from}><SelectInput value={form.data.from} onChange={(value) => { form.setData((data) => ({ ...data, from: value, to: '' })); setLines([]); setWriteOffs([]); }} options={holders} /></FormField>
          <FormField label={f('to')} error={errors.to}><SelectInput value={form.data.to} onChange={(value) => form.setData('to', value)} options={holders.filter((holder) => holder.company_id === fromCompany && holder.value !== form.data.from && !(String(holder.value).startsWith('warehouse:') && !fromEmployee))} /></FormField>
          <FormField label={f('purpose')}><Input value={form.data.purpose} onChange={(event) => form.setData('purpose', event.target.value)} /></FormField>
          <FormField label={f('note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <div className="sm:col-span-2">
            <LinesEditor lines={lines} onChange={setLines} catalog={catalog} assets={assets} stock={stock} holder={form.data.from} error={errors.lines ?? errors.quantity ?? Object.entries(errors).find(([key]) => key.startsWith('lines.'))?.[1]} />
          </div>
          {fromEmployee && held.length > 0 && (
            <fieldset className="flex flex-col gap-2 rounded-md bg-amber-50 p-3 sm:col-span-2">
              <legend className="text-sm font-medium">{t('assets.handovers.write_offs')}</legend>
              <p className="text-xs text-amber-800">{t('assets.handovers.write_offs_hint')}</p>
              {writeOffs.map((row, index) => (
                <div key={index} className="grid grid-cols-2 gap-2 md:grid-cols-[1fr_8rem_5rem_1fr_auto]">
                  <SelectInput value={row.variant_id} onChange={(value) => setWriteOffs(writeOffs.map((item, i) => (i === index ? { ...item, variant_id: Number(value) } : item)))} options={held.map((variant) => ({ value: variant.id, label: variant.label }))} />
                  <SelectInput value={row.kind} onChange={(kind) => setWriteOffs(writeOffs.map((item, i) => (i === index ? { ...item, kind } : item)))} options={kinds} />
                  <Input dir="ltr" inputMode="decimal" value={row.quantity} onChange={(event) => setWriteOffs(writeOffs.map((item, i) => (i === index ? { ...item, quantity: event.target.value } : item)))} />
                  <Input placeholder={t('assets.incidents.fields.description')} value={row.description} onChange={(event) => setWriteOffs(writeOffs.map((item, i) => (i === index ? { ...item, description: event.target.value } : item)))} />
                  <Button type="button" size="icon-sm" variant="ghost" onClick={() => setWriteOffs(writeOffs.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
                </div>
              ))}
              <Button type="button" size="xs" variant="outline" className="self-start" onClick={() => setWriteOffs([...writeOffs, { variant_id: held[0].id, kind: 'broken', quantity: '1', description: '' }])}><Plus data-icon="inline-start" />{t('assets.handovers.add_write_off')}</Button>
            </fieldset>
          )}
          <div className="sm:col-span-2"><PhotoInput files={form.data.photos} onChange={(files) => form.setData('photos', files)} error={errors.photos ?? errors['photos.0']} /></div>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing || lines.length === 0 || !form.data.to}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Handovers({ handovers, filters: initialFilters, statuses, ...rest }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`assets.handovers.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.handovers.title')}>
      <PageHeader title={t('assets.handovers.title')} actions={can('assets.handovers.create') && (rest.warehouses.length > 0 || rest.people.length > 0) && <NewHandover {...rest} />} />
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[{ key: 'status', label: f('status'), options: statuses }]} />
      <DataTable<HandoverSummary>
        rows={handovers}
        rowHref={(row) => url(`/assets/inventory/handovers/${row.id}`)}
        empty={t('assets.handovers.none')}
        columns={[
          { key: 'number', label: f('number'), render: (row) => <span dir="ltr" className="font-mono text-xs">{row.number}</span> },
          { key: 'route', label: `${f('from')} → ${f('to')}`, render: (row) => <span>{row.from.name} → {row.to.name}{row.purpose && <span className="block text-xs text-muted-foreground">{row.purpose}</span>}</span> },
          { key: 'lines', label: f('lines'), render: (row) => row.lines },
          { key: 'date', label: f('date'), render: (row) => <span>{formatDate(row.accepted_at ?? row.created_at, locale)}<span className="block text-xs text-muted-foreground">{row.channel === 'mobile' ? '📱' : '🖥'}</span></span> },
          { key: 'status', label: f('status'), render: (row) => <StatusBadge file="assets" group="handover_status" value={row.status} /> },
        ]}
      />
    </SuiteLayout>
  );
}
