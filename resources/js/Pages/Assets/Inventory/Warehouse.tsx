import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { PackagePlus, Plus, Trash2 } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { CatalogVariant } from '@/types/inventory';

interface StockRow { variant_id: number; label: string; sku: string | null; quantity: string; unit: string; value: string; low: boolean; [key: string]: unknown }
interface MoveRow { id: number; at: string; label: string; reason: string; quantity: string; document: string | null; [key: string]: unknown }
interface Props {
  warehouse: { id: number; code: string; name: string; kind: string; keeper: string | null };
  stock: StockRow[];
  assets: { id: number; tag: string; name: string; status: string }[];
  moves: MoveRow[];
  catalog: CatalogVariant[];
}

interface ReceiptLine { variant_id: number | ''; quantity: string; unit: string; unit_cost: string }

function ReceiveDialog({ warehouseId, catalog }: { warehouseId: number; catalog: CatalogVariant[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.inventory.fields.${field}`);
  const form = useForm<{ lines: ReceiptLine[] }>({ lines: [{ variant_id: catalog[0]?.id ?? '', quantity: '', unit: '', unit_cost: '' }] });
  const errors = form.errors as Record<string, string>;
  const setLine = (index: number, patch: Partial<ReceiptLine>) => form.setData('lines', form.data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><PackagePlus data-icon="inline-start" />{t('assets.inventory.receive')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-w-3xl">
        <form onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ lines: data.lines.map((line) => ({ ...line, unit: line.unit || null, unit_cost: line.unit_cost || null })) })); form.post(url(`/assets/inventory/warehouses/${warehouseId}/receive`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } }); }} className="flex flex-col gap-3">
          <DialogHeader><DialogTitle>{t('assets.inventory.receive')}</DialogTitle></DialogHeader>
          {form.data.lines.map((line, index) => {
            const variant = catalog.find((item) => item.id === line.variant_id);
            return (
              <div key={index} className="grid grid-cols-2 gap-2 md:grid-cols-[1fr_6rem_10rem_7rem_auto]">
                <SelectInput value={line.variant_id} onChange={(value) => setLine(index, { variant_id: Number(value), unit: '' })} options={catalog.map((item) => ({ value: item.id, label: item.label }))} />
                <Input dir="ltr" inputMode="decimal" aria-label={f('quantity')} placeholder={f('quantity')} value={line.quantity} onChange={(event) => setLine(index, { quantity: event.target.value })} />
                <SelectInput value={line.unit} onChange={(unit) => setLine(index, { unit })} options={variant?.units ?? []} />
                <Input dir="ltr" inputMode="decimal" aria-label={f('unit_cost')} placeholder={f('unit_cost')} value={line.unit_cost} onChange={(event) => setLine(index, { unit_cost: event.target.value })} />
                <Button type="button" size="icon-sm" variant="ghost" disabled={form.data.lines.length === 1} onClick={() => form.setData('lines', form.data.lines.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
              </div>
            );
          })}
          {Object.entries(errors).slice(0, 2).map(([key, message]) => <p key={key} className="text-sm text-destructive">{message}</p>)}
          <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('lines', [...form.data.lines, { variant_id: catalog[0]?.id ?? '', quantity: '', unit: '', unit_cost: '' }])}><Plus data-icon="inline-start" />{t('assets.inventory.add_line')}</Button>
          <DialogFooter><Button type="submit" disabled={form.processing || catalog.length === 0}>{t('assets.inventory.receive')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Warehouse({ warehouse, stock, assets, moves, catalog }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const f = (field: string) => t(`assets.inventory.fields.${field}`);

  return (
    <SuiteLayout title={warehouse.name}>
      <PageHeader title={<span>{warehouse.name} <span dir="ltr" className="font-mono text-sm text-muted-foreground">{warehouse.code}</span></span>} description={`${t(`assets.warehouse_kind.${warehouse.kind}`)}${warehouse.keeper ? ` · ${warehouse.keeper}` : ''}`} actions={can('assets.inventory.create') && <ReceiveDialog warehouseId={warehouse.id} catalog={catalog} />} />
      <DataTable<StockRow>
        rows={stock}
        empty={t('assets.inventory.none')}
        columns={[
          { key: 'label', label: f('item'), render: (row) => <span className={cn('font-medium', row.low && 'text-amber-700')}>{row.label}{row.sku && <span dir="ltr" className="ms-2 font-mono text-xs text-muted-foreground">{row.sku}</span>}</span> },
          { key: 'quantity', label: t('assets.inventory.on_hand'), render: (row) => <span className="tabular-nums">{formatNumber(row.quantity, locale)} {row.unit}</span> },
          { key: 'value', label: t('assets.inventory.value'), render: (row) => <span className="tabular-nums">{formatMoney(row.value, locale)}</span> },
        ]}
      />
      {assets.length > 0 && (
        <>
          <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.register.title')}</h2>
          <ul className="flex flex-col divide-y rounded-md border text-sm">{assets.map((asset) => <li key={asset.id} className="flex items-center justify-between p-2"><span><span dir="ltr" className="font-mono text-xs">{asset.tag}</span> {asset.name}</span><StatusBadge file="assets" group="asset_status" value={asset.status} /></li>)}</ul>
        </>
      )}
      <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.inventory.moves')}</h2>
      <DataTable<MoveRow>
        rows={moves}
        empty={t('assets.inventory.none')}
        columns={[
          { key: 'at', label: t('assets.transfers.fields.date'), render: (row) => <span dir="ltr" className="tabular-nums text-xs">{new Date(row.at).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' })}</span> },
          { key: 'label', label: f('item'), render: (row) => row.label },
          { key: 'reason', label: '', render: (row) => t(`assets.inventory.move_reason.${row.reason}`) },
          { key: 'quantity', label: f('quantity'), render: (row) => <span dir="ltr" className={cn('tabular-nums', row.quantity.startsWith('+') ? 'text-emerald-700' : 'text-destructive')}>{row.quantity}</span> },
        ]}
      />
    </SuiteLayout>
  );
}
