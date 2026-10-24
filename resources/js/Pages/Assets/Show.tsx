import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Archive, Pencil, Undo2, UserPlus, Wrench } from 'lucide-react';
import { AssetFields, blankToNull, type AssetFormData } from '@/Pages/Assets/Index';
import DataTable from '@/components/platform/DataTable';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface AssetDetail {
  id: number; tag: string; name: string; name_ar: string; name_en: string; serial: string | null; model: string | null; category_id: number; category: string; branch_id: number;
  notes: string | null; useful_life_months: number | null; life_months: number; status: string; purchase_date: string | null; disposed_on: string | null; disposal_reason: string | null;
  cost: string | null; salvage_value: string | null; book_value: string | null; disposal_amount: string | null;
}
interface CustodyRow { id: number; employee: string | null; assigned_on: string; returned_on: string | null; condition_out: string | null; condition_in: string | null; note: string | null; [key: string]: unknown }
interface JobRow { id: number; kind: string; description: string; opened_on: string; closed_on: string | null; vendor: string | null; resolution: string | null; cost: string | null; reported_by: string | null; [key: string]: unknown }
interface HistoryRow { id: number; event: string; actor: string | null; at: string }

interface Props {
  asset: AssetDetail;
  schedule: { year: number; date: string; book_value: string }[];
  custodies: CustodyRow[];
  maintenance: JobRow[];
  staff: Option[];
  categories: Option[];
  branches: Option[];
  kinds: Option[];
  history: HistoryRow[];
}

function useDialog() {
  const [open, setOpen] = useState(false);
  return { open, setOpen, close: () => setOpen(false) };
}

function EditAsset({ asset, branches, categories }: { asset: AssetDetail; branches: Option[]; categories: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const form = useForm<AssetFormData>({
    branch_id: asset.branch_id, category_id: asset.category_id, name_ar: asset.name_ar, name_en: asset.name_en, serial: asset.serial ?? '', model: asset.model ?? '',
    purchase_date: asset.purchase_date ?? '', cost: asset.cost ?? '', salvage_value: asset.salvage_value ?? '', useful_life_months: asset.useful_life_months ?? '', notes: asset.notes ?? '',
  });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <form onSubmit={(event) => { event.preventDefault(); form.transform(blankToNull); form.put(url(`/assets/assets/${asset.id}`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{asset.tag}</DialogTitle></DialogHeader>
          <AssetFields form={form} branches={branches} categories={categories} showCosts={asset.cost !== null} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function AssignDialog({ assetId, staff }: { assetId: number; staff: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const f = (field: string) => t(`assets.custody.fields.${field}`);
  const form = useForm({ employee_id: staff[0]?.value ?? '', assigned_on: new Date().toISOString().slice(0, 10), condition: '', note: '' });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="sm"><UserPlus data-icon="inline-start" />{t('assets.custody.assign')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/assets/assets/${assetId}/custody`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.custody.assign')}</DialogTitle></DialogHeader>
          <FormField className="sm:col-span-2" label={f('employee')} error={form.errors.employee_id}><SelectInput value={form.data.employee_id} onChange={(value) => form.setData('employee_id', Number(value))} options={staff} /></FormField>
          <FormField label={f('assigned_on')} error={form.errors.assigned_on}><Input dir="ltr" type="date" value={form.data.assigned_on} onChange={(event) => form.setData('assigned_on', event.target.value)} /></FormField>
          <FormField label={f('condition')}><Input value={form.data.condition} onChange={(event) => form.setData('condition', event.target.value)} /></FormField>
          <FormField className="sm:col-span-2" label={f('note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          {(form.errors as Record<string, string>).asset && <p className="text-sm text-destructive sm:col-span-2">{(form.errors as Record<string, string>).asset}</p>}
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing || staff.length === 0}>{t('assets.custody.assign')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ReturnDialog({ custody }: { custody: CustodyRow }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const f = (field: string) => t(`assets.custody.fields.${field}`);
  const form = useForm({ returned_on: new Date().toISOString().slice(0, 10), condition: '' });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline"><Undo2 data-icon="inline-start" />{t('assets.custody.return')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url(`/assets/custody/${custody.id}/return`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.custody.return')} · {custody.employee}</DialogTitle></DialogHeader>
          <FormField label={f('returned_on')} error={form.errors.returned_on}><Input dir="ltr" type="date" min={custody.assigned_on} value={form.data.returned_on} onChange={(event) => form.setData('returned_on', event.target.value)} /></FormField>
          <FormField label={f('condition')}><Input value={form.data.condition} onChange={(event) => form.setData('condition', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('assets.custody.return')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function OpenJob({ assetId, kinds, staff }: { assetId: number; kinds: Option[]; staff: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const f = (field: string) => t(`assets.maintenance.fields.${field}`);
  const form = useForm({ kind: 'repair', description: '', opened_on: new Date().toISOString().slice(0, 10), vendor: '', reported_by_employee_id: '' as number | '' });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><Wrench data-icon="inline-start" />{t('assets.maintenance.open')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ ...data, vendor: data.vendor || null, reported_by_employee_id: data.reported_by_employee_id || null })); form.post(url(`/assets/assets/${assetId}/maintenance`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.maintenance.open')}</DialogTitle></DialogHeader>
          <FormField label={f('kind')}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds} /></FormField>
          <FormField label={f('opened_on')} error={form.errors.opened_on}><Input dir="ltr" type="date" value={form.data.opened_on} onChange={(event) => form.setData('opened_on', event.target.value)} /></FormField>
          <FormField className="sm:col-span-2" label={f('description')} error={form.errors.description}><Input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
          <FormField label={f('vendor')}><Input value={form.data.vendor} onChange={(event) => form.setData('vendor', event.target.value)} /></FormField>
          <FormField label={f('reported_by')}><SelectInput value={form.data.reported_by_employee_id} onChange={(value) => form.setData('reported_by_employee_id', value === '' ? '' : Number(value))} options={staff} emptyLabel="—" /></FormField>
          {(form.errors as Record<string, string>).asset && <p className="text-sm text-destructive sm:col-span-2">{(form.errors as Record<string, string>).asset}</p>}
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CloseJob({ job, showCosts }: { job: JobRow; showCosts: boolean }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const f = (field: string) => t(`assets.maintenance.fields.${field}`);
  const form = useForm({ closed_on: new Date().toISOString().slice(0, 10), cost: '', resolution: '' });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline">{t('assets.maintenance.close')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ ...data, cost: data.cost || null, resolution: data.resolution || null })); form.put(url(`/assets/maintenance/${job.id}/close`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.maintenance.close')}</DialogTitle></DialogHeader>
          <FormField label={f('closed_on')} error={form.errors.closed_on}><Input dir="ltr" type="date" min={job.opened_on} value={form.data.closed_on} onChange={(event) => form.setData('closed_on', event.target.value)} /></FormField>
          {showCosts && <FormField label={f('cost')} error={form.errors.cost}><Input dir="ltr" inputMode="decimal" value={form.data.cost} onChange={(event) => form.setData('cost', event.target.value)} /></FormField>}
          <FormField className="sm:col-span-2" label={f('resolution')}><Input value={form.data.resolution} onChange={(event) => form.setData('resolution', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('assets.maintenance.close')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function DisposeDialog({ asset }: { asset: AssetDetail }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const dialog = useDialog();
  const f = (field: string) => t(`assets.register.fields.${field}`);
  const form = useForm({ as: 'disposed', on: new Date().toISOString().slice(0, 10), amount: '', reason: '' });

  return (
    <Dialog open={dialog.open} onOpenChange={dialog.setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="ghost"><Archive data-icon="inline-start" />{t('assets.register.dispose')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ ...data, amount: data.amount || null })); form.post(url(`/assets/assets/${asset.id}/dispose`), { preserveScroll: true, onSuccess: dialog.close }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('assets.register.dispose')} · {asset.tag}</DialogTitle></DialogHeader>
          <FormField label={f('as')}><SelectInput value={form.data.as} onChange={(value) => form.setData('as', value)} options={[{ value: 'disposed', label: t('assets.asset_status.disposed') }, { value: 'lost', label: t('assets.asset_status.lost') }]} /></FormField>
          <FormField label={f('on')} error={form.errors.on}><Input dir="ltr" type="date" value={form.data.on} onChange={(event) => form.setData('on', event.target.value)} /></FormField>
          {asset.cost !== null && form.data.as === 'disposed' && <FormField label={f('amount')} error={form.errors.amount}><Input dir="ltr" inputMode="decimal" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} /></FormField>}
          <FormField label={f('reason')}><Input value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></FormField>
          {(form.errors as Record<string, string>).status && <p className="text-sm text-destructive sm:col-span-2">{(form.errors as Record<string, string>).status}</p>}
          <DialogFooter className="sm:col-span-2"><Button type="submit" variant="destructive" disabled={form.processing}>{t('assets.register.dispose')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function AssetShow({ asset, schedule, custodies, maintenance, staff, categories, branches, kinds, history }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const f = (field: string) => t(`assets.register.fields.${field}`);
  const showCosts = asset.cost !== null;
  const writtenOff = ['disposed', 'lost'].includes(asset.status);
  const openCustody = custodies.find((custody) => custody.returned_on === null);

  return (
    <SuiteLayout title={`${asset.tag} · ${asset.name}`}>
      <PageHeader
        title={<span className="flex flex-wrap items-center gap-2"><span dir="ltr" className="font-mono">{asset.tag}</span> {asset.name} <StatusBadge file="assets" group="asset_status" value={asset.status} />{!writtenOff && can('assets.register.update') && <EditAsset asset={asset} branches={branches} categories={categories} />}</span>}
        description={asset.category}
        actions={!writtenOff && (
          <div className="flex flex-wrap gap-2">
            {asset.status === 'available' && can('assets.register.assign') && <AssignDialog assetId={asset.id} staff={staff} />}
            {!['maintenance'].includes(asset.status) && can('assets.maintenance.create') && <OpenJob assetId={asset.id} kinds={kinds} staff={staff} />}
            {can('assets.register.dispose') && <DisposeDialog asset={asset} />}
          </div>
        )}
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardContent>
            <DetailList items={[
              { label: f('serial'), value: asset.serial && <span dir="ltr">{asset.serial}</span> },
              { label: f('model'), value: asset.model },
              { label: f('purchase_date'), value: asset.purchase_date && formatDate(asset.purchase_date, locale) },
              { label: f('life'), value: t('assets.register.months', { count: asset.life_months }) },
              showCosts && { label: f('cost'), value: formatMoney(asset.cost!, locale) },
              showCosts && { label: f('book_value'), value: <strong>{formatMoney(asset.book_value!, locale)}</strong> },
              openCustody && { label: f('holder'), value: openCustody.employee },
              writtenOff && { label: f('on'), value: asset.disposed_on && formatDate(asset.disposed_on, locale) },
              writtenOff && { label: f('reason'), value: asset.disposal_reason },
              writtenOff && showCosts && { label: f('amount'), value: asset.disposal_amount && formatMoney(asset.disposal_amount, locale) },
              { label: f('notes'), value: asset.notes, wide: true },
            ]} />
          </CardContent>
        </Card>
        {schedule.length > 0 && (
          <Card>
            <CardHeader><CardTitle className="text-base">{t('assets.register.depreciation')}</CardTitle></CardHeader>
            <CardContent>
              <ul className="flex flex-col divide-y text-sm">
                {schedule.map((row) => <li key={row.year} className="flex justify-between py-1.5"><span dir="ltr" className="tabular-nums">{row.date}</span><span className="tabular-nums">{formatMoney(row.book_value, locale)}</span></li>)}
              </ul>
            </CardContent>
          </Card>
        )}
      </div>

      <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.custody.title')}</h2>
      <DataTable<CustodyRow>
        rows={custodies}
        empty={t('assets.custody.none')}
        columns={[
          { key: 'employee', label: t('assets.custody.fields.employee'), render: (row) => <span className="font-medium">{row.employee}</span> },
          { key: 'assigned_on', label: t('assets.custody.fields.assigned_on'), render: (row) => <span><span dir="ltr" className="tabular-nums">{row.assigned_on}</span>{row.condition_out && <span className="block text-xs text-muted-foreground">{row.condition_out}</span>}</span> },
          { key: 'returned_on', label: t('assets.custody.fields.returned_on'), render: (row) => (row.returned_on ? <span><span dir="ltr" className="tabular-nums">{row.returned_on}</span>{row.condition_in && <span className="block text-xs text-muted-foreground">{row.condition_in}</span>}</span> : can('assets.register.assign') ? <ReturnDialog custody={row} /> : '—') },
          { key: 'note', label: t('assets.custody.fields.note'), render: (row) => row.note },
        ]}
      />

      <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.maintenance.title')}</h2>
      <DataTable<JobRow>
        rows={maintenance}
        empty={t('assets.maintenance.none')}
        columns={[
          { key: 'description', label: t('assets.maintenance.fields.description'), render: (job) => <span><span className="block font-medium">{job.description}</span><span className="text-xs text-muted-foreground">{t(`assets.maintenance_kind.${job.kind}`)}{job.reported_by ? ` · ${job.reported_by}` : ''}{job.vendor ? ` · ${job.vendor}` : ''}</span></span> },
          { key: 'opened_on', label: t('assets.maintenance.fields.opened_on'), render: (job) => <span dir="ltr" className="tabular-nums">{job.opened_on}</span> },
          { key: 'closed_on', label: t('assets.maintenance.fields.closed_on'), render: (job) => (job.closed_on ? <span><span dir="ltr" className="tabular-nums">{job.closed_on}</span>{job.resolution && <span className="block text-xs text-muted-foreground">{job.resolution}</span>}</span> : can('assets.maintenance.update') ? <CloseJob job={job} showCosts={showCosts} /> : '—') },
          ...(showCosts ? [{ key: 'cost', label: t('assets.maintenance.fields.cost'), render: (job: JobRow) => (job.cost ? <span className="tabular-nums">{formatMoney(job.cost, locale)}</span> : '—') }] : []),
        ]}
      />

      {history.length > 0 && (
        <>
          <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.register.history')}</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {history.map((event) => <li key={event.id} className="flex gap-3"><span dir="ltr" className="w-36 shrink-0 tabular-nums text-muted-foreground">{new Date(event.at).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' })}</span><span>{t(`assets.events.${event.event}`)}{event.actor ? ` · ${event.actor}` : ''}</span></li>)}
          </ul>
        </>
      )}
    </SuiteLayout>
  );
}
