import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CheckCircle2, Cpu, Plus } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useCopy, useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface Unit { id: number; name: string; company: string | null; serial: string; environment: string; status: string; counter: number; has_compliance: boolean; has_production: boolean }
interface Readiness { company_id: number; company: string; missing: string[]; step: number }
interface Props { readiness: Readiness[]; units: Unit[]; companies: (Option & { ready: boolean })[]; branches: (Option & { company_id: number })[]; environments: Option[]; can_onboard: boolean }

function NewUnitDialog({ companies, branches, environments }: Pick<Props, 'companies' | 'branches' | 'environments'>) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm<{ company_id: number | ''; branch_id: number | ''; name: string; environment: string }>({ company_id: (companies[0]?.value as number) ?? '', branch_id: '', name: '', environment: 'sandbox' });
  const f = (field: string) => t(`invoicing.units.fields.${field}`);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Plus data-icon="inline-start" />{t('invoicing.units.new')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url('/invoicing/units'), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('invoicing.units.new')}</DialogTitle></DialogHeader>
          <FormField label={f('company')} error={form.errors.company_id} hint={companies.some((company) => !company.ready) ? t('invoicing.errors.company_vat') : undefined}><SelectInput value={form.data.company_id} onChange={(value) => form.setData((current) => ({ ...current, company_id: Number(value), branch_id: '' }))} options={companies} /></FormField>
          <FormField label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value === '' ? '' : Number(value))} options={branches.filter((branch) => branch.company_id === Number(form.data.company_id))} emptyLabel="—" /></FormField>
          <FormField label={f('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="POS-1" /></FormField>
          <FormField label={f('environment')} error={form.errors.environment}><SelectInput value={form.data.environment} onChange={(value) => form.setData('environment', value)} options={environments} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function OtpForm({ unit }: { unit: Unit }) {
  const t = useT();
  const url = useSuiteUrl();
  const form = useForm({ otp: '' });

  return (
    <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/invoicing/units/${unit.id}/compliance`), { preserveScroll: true }); }} className="flex items-end gap-2">
      <FormField label={t('invoicing.units.otp')} error={form.errors.otp}><Input dir="ltr" inputMode="numeric" maxLength={6} className="w-28" value={form.data.otp} onChange={(event) => form.setData('otp', event.target.value)} /></FormField>
      <Button type="submit" size="sm" disabled={form.processing}>{t('invoicing.units.request_csid')}</Button>
    </form>
  );
}

function ReadinessCard({ readiness }: { readiness: Readiness }) {
  const t = useT();
  const steps = useCopy<string[] | undefined>('invoicing.readiness.steps') ?? [];

  return (
    <Card><CardContent className="flex flex-col gap-3">
      <div className="font-medium">{readiness.company}</div>
      <ol className="grid gap-2 sm:grid-cols-5">
        {steps.map((label, index) => {
          const position = index + 1;
          const state = position < readiness.step || readiness.step === 5 ? 'done' : position === readiness.step ? 'current' : 'todo';

          return (
            <li key={label} className={cn('flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs', state === 'done' && 'border-emerald-200 bg-emerald-50 text-emerald-800', state === 'current' && 'border-brass font-medium', state === 'todo' && 'text-muted-foreground')}>
              {state === 'done' ? <CheckCircle2 className="size-4 shrink-0" /> : <span className="grid size-4 shrink-0 place-items-center rounded-full border text-[10px]">{position}</span>}
              <span>{label}</span>
            </li>
          );
        })}
      </ol>
      {readiness.missing.length > 0 && (
        <p className="text-xs text-muted-foreground">{t('invoicing.readiness.missing', { fields: readiness.missing.map((field) => t(`invoicing.readiness.fields.${field}`)).join('، ') })} — {t('invoicing.readiness.fix_company')}</p>
      )}
    </CardContent></Card>
  );
}

export default function UnitsIndex({ readiness, units, companies, branches, environments, can_onboard: canOnboard }: Props) {
  const t = useT();
  const url = useSuiteUrl();

  return (
    <SuiteLayout title={t('invoicing.units.title')}>
      <PageHeader title={t('invoicing.units.title')} description={t('invoicing.units.lede')} actions={canOnboard && <NewUnitDialog companies={companies} branches={branches} environments={environments} />} />
      {readiness.some((row) => row.step < 5) && (
        <section className="mb-6 flex flex-col gap-3">
          <h2 className="text-sm font-semibold">{t('invoicing.readiness.title')}</h2>
          {readiness.filter((row) => row.step < 5).map((row) => <ReadinessCard key={row.company_id} readiness={row} />)}
        </section>
      )}
      <div className="grid gap-3 md:grid-cols-2">
        {units.length === 0 && <Card className="md:col-span-2"><CardContent className="py-8 text-center text-muted-foreground">{t('common.actions.empty')}</CardContent></Card>}
        {units.map((unit) => (
          <Card key={unit.id}><CardContent className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <div className="flex items-center gap-2 font-medium"><Cpu className="size-4 text-brass" />{unit.name}</div>
                <div className="text-xs text-muted-foreground">{unit.company} · {t(`invoicing.zatca_environment.${unit.environment}`)} · {t('invoicing.units.counter')} {unit.counter}</div>
                <div className="mt-1 truncate font-mono text-[10px] text-muted-foreground" dir="ltr">{unit.serial}</div>
              </div>
              <StatusBadge group="unit_status" value={unit.status} file="invoicing" />
            </div>
            {canOnboard && unit.status === 'new' && <OtpForm unit={unit} />}
            {canOnboard && unit.status === 'compliance' && (
              <ConfirmAction href={url(`/invoicing/units/${unit.id}/production`)} method="post" size="sm" variant="outline" title={t('invoicing.units.request_production')}>{t('invoicing.units.request_production')}</ConfirmAction>
            )}
          </CardContent></Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
