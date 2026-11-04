import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CountsEditor, PhotoGrid, PhotoInput, countsPayload } from '@/components/assets/InventoryWidgets';
import type { HandoverSummary } from '@/Pages/Assets/Inventory/Handovers';
import { IncidentList } from '@/Pages/Assets/Inventory/Incidents';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { DocumentLine, Explanation, IncidentSummary } from '@/types/inventory';
import type { Option } from '@/types/resources';

interface Props {
  handover: HandoverSummary;
  lines: DocumentLine[];
  incidents: IncidentSummary[];
  photos: { id: number; url: string; stage: string | null }[];
  can: { accept: boolean; cancel: boolean };
  kinds: Option[];
}

export default function Handover({ handover, lines, incidents, photos, can, kinds }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [explanations, setExplanations] = useState<Record<number, Explanation[]>>({});
  const accept = useForm<{ photos: File[] }>({ photos: [] });
  const reject = useForm({ reason: '' });
  const errors = accept.errors as Record<string, string>;

  return (
    <SuiteLayout title={`${t('assets.handovers.title')} ${handover.number}`}>
      <PageHeader
        title={<span className="flex items-center gap-2"><span dir="ltr" className="font-mono">{handover.number}</span><StatusBadge file="assets" group="handover_status" value={handover.status} /></span>}
        description={`${handover.from.name} → ${handover.to.name}${handover.purpose ? ` · ${handover.purpose}` : ''}`}
      />

      {can.accept ? (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{t('assets.handovers.accept')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">{t('assets.handovers.counts_hint')}</p>
            <CountsEditor lines={lines} counts={counts} explanations={explanations} onCounts={setCounts} onExplanations={setExplanations} kinds={kinds} errors={errors} />
            <PhotoInput files={accept.data.photos} onChange={(files) => accept.setData('photos', files)} error={errors.photos} />
            <div className="flex flex-wrap items-center gap-2">
              <Button disabled={accept.processing} onClick={() => { accept.transform((data) => ({ ...data, ...countsPayload(counts, explanations) })); accept.post(url(`/assets/inventory/handovers/${handover.id}/accept`), { preserveScroll: true }); }}>{t('assets.handovers.accept')}</Button>
              <Input className="w-56" placeholder={t('assets.incidents.fields.note')} value={reject.data.reason} onChange={(event) => reject.setData('reason', event.target.value)} />
              <Button variant="ghost" disabled={reject.processing} onClick={() => reject.post(url(`/assets/inventory/handovers/${handover.id}/reject`), { preserveScroll: true })}>{t('assets.handovers.reject')}</Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="mb-4">
          <CardContent className="py-4">
            <ul className="flex flex-col divide-y text-sm">
              {lines.map((line) => (
                <li key={line.id} className="flex items-center justify-between gap-2 py-2">
                  <span><span className="font-medium">{line.label}</span>{line.condition && <span className="text-xs text-muted-foreground"> · {line.condition}</span>}</span>
                  <span className="tabular-nums">{formatNumber(line.quantity, locale)} {line.unit ?? ''}{line.received !== null && <span className={Number(line.received) < Number(line.quantity) ? ' text-destructive' : ' text-emerald-700'}> → {formatNumber(line.received, locale)}</span>}</span>
                </li>
              ))}
            </ul>
            {can.cancel && <Button className="mt-3" variant="ghost" size="sm" onClick={() => reject.post(url(`/assets/inventory/handovers/${handover.id}/reject`), { preserveScroll: true })}>{t('assets.handovers.cancel')}</Button>}
          </CardContent>
        </Card>
      )}

      <PhotoGrid photos={photos} />
      {incidents.length > 0 && <div className="mt-4"><IncidentList incidents={incidents} /></div>}
    </SuiteLayout>
  );
}
