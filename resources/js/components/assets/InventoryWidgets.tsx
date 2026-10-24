import { useRef, useState } from 'react';
import { Camera, Plus, ScanLine, Trash2 } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import type { CatalogVariant, DocumentLine, DraftLine, Explanation, MovableAsset, StockMap } from '@/types/inventory';
import type { Option } from '@/types/resources';

/** Find what a scanner (or a person) typed: an asset tag, a barcode or SKU, or part of a name. */
export function findByCode(code: string, catalog: CatalogVariant[], assets: MovableAsset[]): { variant?: CatalogVariant; asset?: MovableAsset } | null {
  const needle = code.trim().toLowerCase();
  if (!needle) {
    return null;
  }
  const asset = assets.find((item) => item.tag.toLowerCase() === needle || item.serial?.toLowerCase() === needle);
  if (asset) {
    return { asset };
  }
  const variant = catalog.find((item) => item.barcode?.toLowerCase() === needle || item.sku?.toLowerCase() === needle)
    ?? catalog.find((item) => item.label.toLowerCase().includes(needle));

  return variant ? { variant } : null;
}

/**
 * Lines of a transfer or handover: scan or type to add, then set quantity and unit (pieces,
 * a pack, or another unit of the same kind). Shows what the sender has on hand.
 */
export function LinesEditor({ lines, onChange, catalog, assets, stock, holder, error }: {
  lines: DraftLine[];
  onChange: (lines: DraftLine[]) => void;
  catalog: CatalogVariant[];
  assets: MovableAsset[];
  stock: StockMap;
  holder: string;
  error?: string;
}) {
  const t = useT();
  const { locale } = useLocale();
  const [code, setCode] = useState('');
  const [notFound, setNotFound] = useState<string | null>(null);
  const onHand = stock[holder] ?? {};
  const heldAssets = assets.filter((asset) => asset.holder === holder);

  function add(event?: React.FormEvent) {
    event?.preventDefault();
    const match = findByCode(code, catalog, heldAssets);
    if (!match) {
      setNotFound(code);
      return;
    }
    setNotFound(null);
    setCode('');
    if (match.asset) {
      if (!lines.some((line) => line.asset_id === match.asset!.id)) {
        onChange([...lines, { key: `a${match.asset.id}`, asset_id: match.asset.id, label: match.asset.label, quantity: '1', unit: '', units: [], condition: '' }]);
      }
      return;
    }
    const variant = match.variant!;
    const existing = lines.find((line) => line.variant_id === variant.id && line.unit === '');
    onChange(existing
      ? lines.map((line) => (line === existing ? { ...line, quantity: String(Number(line.quantity) + 1) } : line))
      : [...lines, { key: `v${variant.id}-${Date.now()}`, variant_id: variant.id, label: variant.label, quantity: '1', unit: '', units: variant.units, condition: '' }]);
  }

  function update(key: string, patch: Partial<DraftLine>) {
    onChange(lines.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="flex gap-2">
        <div className="relative flex-1">
          <ScanLine className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            dir="ltr" className="ps-8" placeholder={t('assets.inventory.scan')} aria-label={t('assets.inventory.scan')} value={code} list="inventory-catalog"
            onChange={(event) => setCode(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { add(event); } }}
          />
          <datalist id="inventory-catalog">
            {catalog.map((variant) => <option key={variant.id} value={variant.label} />)}
            {heldAssets.map((asset) => <option key={`a${asset.id}`} value={asset.tag}>{asset.label}</option>)}
          </datalist>
        </div>
        <Button type="button" variant="outline" onClick={() => add()} disabled={!code}><Plus data-icon="inline-start" />{t('assets.inventory.add_line')}</Button>
      </div>
      {notFound && <p className="text-sm text-destructive">{t('assets.inventory.scan_unknown', { code: notFound })}</p>}
      {error && <p className="text-sm text-destructive">{error}</p>}
      <ul className="flex flex-col divide-y rounded-md border">
        {lines.map((line) => (
          <li key={line.key} className="grid grid-cols-[1fr_auto] items-center gap-2 p-2 text-sm sm:grid-cols-[1fr_6rem_10rem_auto]">
            <span>
              <span className="font-medium">{line.label}</span>
              {line.variant_id && <span className="block text-xs text-muted-foreground">{t('assets.inventory.available', { quantity: formatNumber(onHand[line.variant_id] ?? '0', locale) })}</span>}
            </span>
            {line.variant_id ? (
              <>
                <Input dir="ltr" inputMode="decimal" aria-label={t('assets.inventory.fields.quantity')} value={line.quantity} onChange={(event) => update(line.key, { quantity: event.target.value })} />
                <SelectInput value={line.unit} onChange={(unit) => update(line.key, { unit })} options={line.units} />
              </>
            ) : (
              <Input className="sm:col-span-2" placeholder={t('assets.custody.fields.condition')} value={line.condition} onChange={(event) => update(line.key, { condition: event.target.value })} />
            )}
            <Button type="button" size="icon-sm" variant="ghost" onClick={() => onChange(lines.filter((item) => item.key !== line.key))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function linesPayload(lines: DraftLine[]) {
  return lines.map((line) => (line.asset_id
    ? { asset_id: line.asset_id, condition: line.condition || null }
    : { variant_id: line.variant_id, quantity: line.quantity, unit: line.unit || null }));
}

/**
 * Counting on arrival: each line's sent quantity, what actually arrived, and what happened to any
 * missing units (broken, lost, stolen…). Anything not explained is recorded as missing.
 */
export function CountsEditor({ lines, counts, explanations, onCounts, onExplanations, kinds, errors }: {
  lines: DocumentLine[];
  counts: Record<number, string>;
  explanations: Record<number, Explanation[]>;
  onCounts: (counts: Record<number, string>) => void;
  onExplanations: (explanations: Record<number, Explanation[]>) => void;
  kinds: Option[];
  errors: Record<string, string>;
}) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <ul className="flex flex-col divide-y rounded-md border">
      {lines.map((line) => {
        const received = counts[line.id] ?? line.quantity;
        const short = Math.max(0, Number(line.quantity) - Number(received || 0));
        const rows = explanations[line.id] ?? [];
        const setRows = (next: Explanation[]) => onExplanations({ ...explanations, [line.id]: next });

        return (
          <li key={line.id} className="flex flex-col gap-2 p-3 text-sm">
            <div className="grid grid-cols-[1fr_7rem] items-center gap-3">
              <span>
                <span className="font-medium">{line.label}</span>
                <span className="block text-xs text-muted-foreground">{t('assets.transfers.fields.sent')}: {formatNumber(line.quantity, locale)} {line.unit ?? ''}{line.entered ? ` (${line.entered})` : ''}</span>
              </span>
              <Input dir="ltr" inputMode="decimal" aria-label={t('assets.transfers.fields.received')} value={received} onChange={(event) => onCounts({ ...counts, [line.id]: event.target.value })} />
            </div>
            {errors[`counts.${line.id}`] && <p className="text-xs text-destructive">{errors[`counts.${line.id}`]}</p>}
            {short > 0 && (
              <div className="flex flex-col gap-2 rounded-md bg-amber-50 p-2">
                <span className="text-xs text-amber-800">{t('assets.handovers.write_offs')} ({formatNumber(short, locale)})</span>
                {rows.map((row, index) => (
                  <div key={index} className="grid grid-cols-[8rem_5rem_1fr_auto] gap-2">
                    <SelectInput value={row.kind} onChange={(kind) => setRows(rows.map((item, i) => (i === index ? { ...item, kind } : item)))} options={kinds} />
                    <Input dir="ltr" inputMode="decimal" value={row.quantity} onChange={(event) => setRows(rows.map((item, i) => (i === index ? { ...item, quantity: event.target.value } : item)))} />
                    <Input placeholder={t('assets.incidents.fields.description')} value={row.description} onChange={(event) => setRows(rows.map((item, i) => (i === index ? { ...item, description: event.target.value } : item)))} />
                    <Button type="button" size="icon-sm" variant="ghost" onClick={() => setRows(rows.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
                  </div>
                ))}
                {errors[`explanations.${line.id}`] && <p className="text-xs text-destructive">{errors[`explanations.${line.id}`]}</p>}
                <Button type="button" size="xs" variant="outline" className="self-start" onClick={() => setRows([...rows, { kind: 'broken', quantity: String(short), description: '' }])}><Plus data-icon="inline-start" />{t('assets.handovers.add_write_off')}</Button>
              </div>
            )}
          </li>
        );
      })}
    </ul>
  );
}

/** Photos from the camera or the gallery (several at once). */
export function PhotoInput({ files, onChange, error }: { files: File[]; onChange: (files: File[]) => void; error?: string }) {
  const t = useT();
  const input = useRef<HTMLInputElement>(null);

  return (
    <FormField label={t('assets.handovers.photos')} error={error}>
      <div className="flex flex-wrap items-center gap-2">
        <input ref={input} type="file" accept="image/*" capture="environment" multiple className="hidden" onChange={(event) => { onChange([...files, ...Array.from(event.target.files ?? [])].slice(0, 10)); event.target.value = ''; }} />
        <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()}><Camera data-icon="inline-start" />{t('assets.handovers.photos')}</Button>
        {files.map((file, index) => (
          <span key={index} className="relative">
            <img src={URL.createObjectURL(file)} alt="" className="size-14 rounded-md object-cover" />
            <button type="button" className="absolute -end-1 -top-1 rounded-full bg-background p-0.5 shadow" onClick={() => onChange(files.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 className="size-3" /></button>
          </span>
        ))}
      </div>
    </FormField>
  );
}

export function PhotoGrid({ photos }: { photos: { id?: number; url: string; stage?: string | null }[] }) {
  if (photos.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-2">
      {photos.map((photo, index) => (
        <a key={photo.id ?? index} href={photo.url} target="_blank" rel="noreferrer" className="block">
          <img src={photo.url} alt="" className="size-24 rounded-md border object-cover" loading="lazy" />
        </a>
      ))}
    </div>
  );
}

/** Flattens the line and explanation maps into what the server expects. */
export function countsPayload(counts: Record<number, string>, explanations: Record<number, Explanation[]>) {
  return {
    counts: Object.fromEntries(Object.entries(counts).filter(([, value]) => value !== '')),
    explanations: Object.fromEntries(Object.entries(explanations).map(([lineId, rows]) => [lineId, rows.filter((row) => Number(row.quantity) > 0).map((row) => ({ ...row, description: row.description || null }))])),
  };
}
