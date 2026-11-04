import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { CountsEditor, PhotoGrid, PhotoInput, countsPayload } from '@/components/assets/InventoryWidgets';
import { IncidentList } from '@/Pages/Assets/Inventory/Incidents';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { DocumentLine, Explanation, IncidentSummary } from '@/types/inventory';
import type { Option } from '@/types/resources';

interface Props {
  transfer: { id: number; number: string; status: string; note: string | null; from: string; to: string; sent_at: string | null; received_at: string | null };
  lines: DocumentLine[];
  incidents: IncidentSummary[];
  photos: { id: number; url: string; stage: string | null }[];
  can: { send: boolean; receive: boolean; cancel: boolean };
  kinds: Option[];
}

export default function Transfer({ transfer, lines, incidents, photos, can, kinds }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [explanations, setExplanations] = useState<Record<number, Explanation[]>>({});
  const send = useForm<{ photos: File[] }>({ photos: [] });
  const receive = useForm<{ photos: File[] }>({ photos: [] });
  const errors = receive.errors as Record<string, string>;

  return (
    <SuiteLayout title={`${t('assets.transfers.title')} ${transfer.number}`}>
      <PageHeader
        title={<span className="flex items-center gap-2"><span dir="ltr" className="font-mono">{transfer.number}</span><StatusBadge file="assets" group="transfer_status" value={transfer.status} /></span>}
        description={`${transfer.from} → ${transfer.to}${transfer.note ? ` · ${transfer.note}` : ''}`}
        actions={can.cancel && <Button variant="ghost" size="sm" onClick={() => router.post(url(`/assets/inventory/transfers/${transfer.id}/cancel`), {}, { preserveScroll: true })}>{t('assets.transfers.cancel')}</Button>}
      />

      {can.receive ? (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{t('assets.transfers.receive')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">{t('assets.handovers.counts_hint')}</p>
            <CountsEditor lines={lines} counts={counts} explanations={explanations} onCounts={setCounts} onExplanations={setExplanations} kinds={kinds} errors={errors} />
            <PhotoInput files={receive.data.photos} onChange={(files) => receive.setData('photos', files)} error={errors.photos} />
            <Button className="self-start" disabled={receive.processing} onClick={() => { receive.transform((data) => ({ ...data, ...countsPayload(counts, explanations) })); receive.post(url(`/assets/inventory/transfers/${transfer.id}/receive`), { preserveScroll: true }); }}>{t('assets.transfers.receive')}</Button>
          </CardContent>
        </Card>
      ) : (
        <Card className="mb-4">
          <CardContent className="py-4">
            <ul className="flex flex-col divide-y text-sm">
              {lines.map((line) => (
                <li key={line.id} className="flex items-center justify-between gap-2 py-2">
                  <span><span className="font-medium">{line.label}</span>{line.entered && <span className="text-xs text-muted-foreground"> ({line.entered})</span>}</span>
                  <span className="tabular-nums">{formatNumber(line.quantity, locale)} {line.unit ?? ''}{line.received !== null && <span className={Number(line.received) < Number(line.quantity) ? ' text-destructive' : ' text-emerald-700'}> → {formatNumber(line.received, locale)}</span>}</span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {can.send && (
        <Card className="mb-4">
          <CardContent className="flex flex-col gap-3 py-4">
            <PhotoInput files={send.data.photos} onChange={(files) => send.setData('photos', files)} error={(send.errors as Record<string, string>).quantity} />
            <Button className="self-start" disabled={send.processing} onClick={() => send.post(url(`/assets/inventory/transfers/${transfer.id}/send`), { preserveScroll: true })}>{t('assets.transfers.send')}</Button>
          </CardContent>
        </Card>
      )}

      <PhotoGrid photos={photos} />
      {incidents.length > 0 && <div className="mt-4"><IncidentList incidents={incidents} /></div>}
    </SuiteLayout>
  );
}
