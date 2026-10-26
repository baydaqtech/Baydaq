import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatNumber, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface CompanyMeter {
  company_id: number;
  company: string;
  saudis: number;
  saudis_headcount: number;
  expats: number;
  total: number;
  percent: number;
  target: number;
  needed: number;
  people: { id: number; name: string; weight: number; reason: 'counts' | 'below_wage' | 'not_authenticated' }[];
}

function Meter({ percent, target }: { percent: number; target: number }) {
  const ok = target <= 0 || percent >= target;

  return (
    <div className="relative h-3 rounded-full bg-muted">
      <div className={cn('h-3 rounded-full', ok ? 'bg-emerald-600' : 'bg-amber-500')} style={{ width: `${Math.min(100, percent)}%` }} />
      {target > 0 && <div className="absolute top-[-4px] h-5 w-0.5 bg-walnut" style={{ insetInlineStart: `${Math.min(100, target)}%` }} />}
    </div>
  );
}

/** One company's Saudization with its target and a what-if simulator (H4). */
function CompanyCard({ meter }: { meter: CompanyMeter }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const c = (key: string, replace?: Record<string, string>) => t(`hr.compliance.${key}`, replace);
  const form = useForm({ company_id: meter.company_id, target: String(meter.target || '') });
  const [addSaudis, setAddSaudis] = useState(0);
  const [removeExpats, setRemoveExpats] = useState(0);
  const fixable = meter.people.reduce((sum, person) => sum + (1 - person.weight), 0);
  const [fix, setFix] = useState(0);
  const saudis = meter.saudis + addSaudis + Math.min(fix, fixable);
  const total = saudis + Math.max(0, meter.expats - removeExpats);
  const simulated = total > 0 ? Math.round((saudis / total) * 1000) / 10 : 0;
  const simulating = addSaudis > 0 || removeExpats > 0 || fix > 0;

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle className="text-base">{meter.company}</CardTitle>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url('/hr/reports/nitaqat'), { preserveScroll: true }); }} className="flex items-center gap-2 text-sm">
          <span className="text-muted-foreground">{c('target')}</span>
          <Input className="w-20" type="number" step="0.1" min="0" max="99" dir="ltr" value={form.data.target} onChange={(event) => form.setData('target', event.target.value)} />
          <Button size="sm" variant="outline" type="submit" disabled={form.processing}>{t('common.actions.save')}</Button>
        </form>
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <div className="flex items-baseline justify-between"><span className="text-2xl font-semibold" dir="ltr">{formatPercent(meter.percent, locale)}</span><span className="text-muted-foreground">{c('saudis')}: {formatNumber(meter.saudis, locale)} · {c('expats')}: {formatNumber(meter.expats, locale)}</span></div>
        <Meter percent={meter.percent} target={meter.target} />
        <p className={cn(meter.needed > 0 ? 'text-amber-700' : 'text-emerald-700')}>{meter.target <= 0 ? c('no_target') : meter.needed > 0 ? c('needed', { count: String(meter.needed) }) : c('on_target')}</p>
        <div className="rounded-md border p-3">
          <h3 className="mb-2 font-medium">{c('what_if')}</h3>
          <div className="grid gap-2 sm:grid-cols-3">
            <label className="flex flex-col gap-1">{c('add_saudis')}<Input type="number" min="0" dir="ltr" value={addSaudis} onChange={(event) => setAddSaudis(Math.max(0, Number(event.target.value)))} /></label>
            <label className="flex flex-col gap-1">{c('remove_expats')}<Input type="number" min="0" max={meter.expats} dir="ltr" value={removeExpats} onChange={(event) => setRemoveExpats(Math.max(0, Number(event.target.value)))} /></label>
            {fixable > 0 && <label className="flex flex-col gap-1">{c('fix_contracts')}<Input type="number" min="0" step="0.5" max={fixable} dir="ltr" value={fix} onChange={(event) => setFix(Math.max(0, Number(event.target.value)))} /></label>}
          </div>
          {simulating && <div className="mt-3 flex flex-col gap-1"><span>{c('simulated')}: <strong dir="ltr">{formatPercent(simulated, locale)}</strong></span><Meter percent={simulated} target={meter.target} /></div>}
        </div>
        {meter.people.some((person) => person.weight < 1) && (
          <ul className="flex flex-wrap gap-1.5">
            {meter.people.filter((person) => person.weight < 1).map((person) => <li key={person.id}><Badge variant="outline">{person.name} · {c(`reason.${person.reason}`)}</Badge></li>)}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

export default function Nitaqat({ companies }: { companies: CompanyMeter[] }) {
  const t = useT();

  return (
    <SuiteLayout title={t('hr.compliance.nitaqat')}>
      <PageHeader title={t('hr.compliance.nitaqat')} description={t('hr.compliance.nitaqat_hint')} />
      <div className="grid gap-4 lg:grid-cols-2">{companies.map((meter) => <CompanyCard key={meter.company_id} meter={meter} />)}</div>
    </SuiteLayout>
  );
}
