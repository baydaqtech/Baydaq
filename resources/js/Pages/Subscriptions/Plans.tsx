import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';
import type { Option } from '@/types/resources';

interface Plan {
  id: number; company_id: number; name: string; name_ar: string; name_en: string; description_ar: string | null; description_en: string | null;
  period_unit: string; period_count: number; price: string; vat_rate: string; sessions: number | null; freeze_days: number; gender: string; is_corporate: boolean; family_size: number; is_active: boolean; branch_ids: number[];
  [key: string]: unknown;
}

interface ZoneRow { id: number; branch_id: number; name: string; gender: string }

interface Props { companies: Option[]; branches: ScopedOption[]; plans: Plan[]; units: Option[]; genders: Option[]; zones: ZoneRow[] }

function PlanDialog({ plan, companies, branches, units, genders }: { plan?: Plan } & Omit<Props, 'plans' | 'zones'>) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`subscriptions.plans.fields.${field}`);
  const form = useForm({
    company_id: plan?.company_id ?? companies[0]?.value ?? '', name_ar: plan?.name_ar ?? '', name_en: plan?.name_en ?? '', description_ar: plan?.description_ar ?? '', description_en: plan?.description_en ?? '',
    period_unit: plan?.period_unit ?? 'month', period_count: plan?.period_count ?? 1, price: plan?.price ?? '', vat_rate: plan ? String(Number(plan.vat_rate)) : '15',
    sessions: plan?.sessions ?? ('' as number | ''), freeze_days: plan?.freeze_days ?? 0, gender: plan?.gender ?? 'any', is_corporate: plan?.is_corporate ?? false, family_size: plan?.family_size ?? 1, is_active: plan?.is_active ?? true, branch_ids: plan?.branch_ids ?? ([] as number[]),
  });
  const companyId = Number(form.data.company_id);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, sessions: data.sessions === '' ? null : data.sessions }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    plan ? form.put(url(`/subscriptions/plans/${plan.id}`), options) : form.post(url('/subscriptions/plans'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{plan ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('subscriptions.plans.new')}</Button>}</DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{plan ? plan.name : t('subscriptions.plans.new')}</DialogTitle></DialogHeader>
          {!plan && companies.length > 1 && <FormField className="sm:col-span-2" label={f('company')}><SelectInput value={form.data.company_id} onChange={(value) => form.setData((data) => ({ ...data, company_id: Number(value), branch_ids: [] }))} options={companies} /></FormField>}
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('description_ar')}><Textarea rows={2} value={form.data.description_ar} onChange={(event) => form.setData('description_ar', event.target.value)} /></FormField>
          <FormField label={f('description_en')}><Textarea dir="ltr" rows={2} value={form.data.description_en} onChange={(event) => form.setData('description_en', event.target.value)} /></FormField>
          <FormField label={f('period_count')} error={form.errors.period_count}><Input dir="ltr" type="number" min={1} value={form.data.period_count} onChange={(event) => form.setData('period_count', Number(event.target.value))} /></FormField>
          <FormField label={f('period_unit')}><SelectInput value={form.data.period_unit} onChange={(value) => form.setData('period_unit', value)} options={units} /></FormField>
          <FormField label={f('price')} error={form.errors.price}><Input dir="ltr" inputMode="decimal" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} /></FormField>
          <FormField label={f('vat_rate')}><SelectInput value={form.data.vat_rate} onChange={(value) => form.setData('vat_rate', value)} options={[{ value: '15', label: '15%' }, { value: '5', label: '5%' }, { value: '0', label: '0%' }]} /></FormField>
          <FormField label={f('sessions')} error={form.errors.sessions}><Input dir="ltr" type="number" min={1} value={form.data.sessions} onChange={(event) => form.setData('sessions', event.target.value === '' ? '' : Number(event.target.value))} /></FormField>
          <FormField label={f('freeze_days')} error={form.errors.freeze_days}><Input dir="ltr" type="number" min={0} value={form.data.freeze_days} onChange={(event) => form.setData('freeze_days', Number(event.target.value))} /></FormField>
          <FormField label={f('gender')}><SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} options={genders} /></FormField>
          <div className="flex items-end"><SwitchField id="plan-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} /></div>
          <FormField label={f('family_size')} hint={t('subscriptions.plans.family_hint')} error={form.errors.family_size}><Input dir="ltr" type="number" min={1} max={10} value={form.data.family_size} onChange={(event) => form.setData('family_size', Number(event.target.value))} /></FormField>
          <div className="flex items-end"><SwitchField id="plan-corporate" label={f('is_corporate')} checked={form.data.is_corporate} onChange={(value: boolean) => form.setData('is_corporate', value)} /></div>
          {form.data.is_corporate && <p className="text-sm text-muted-foreground sm:col-span-2">{t('subscriptions.corporate.hint')}</p>}
          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{f('branches')}</legend>
            <div className="flex flex-wrap gap-3">
              {branches.filter((branch) => branch.company_id === companyId).map((branch) => (
                <label key={branch.value} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.data.branch_ids.includes(Number(branch.value))} onCheckedChange={(checked) => form.setData('branch_ids', checked ? [...form.data.branch_ids, Number(branch.value)] : form.data.branch_ids.filter((id) => id !== Number(branch.value)))} />
                  {branch.label}
                </label>
              ))}
            </div>
            {form.errors.branch_ids && <p className="text-sm text-destructive">{form.errors.branch_ids}</p>}
          </fieldset>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Plans(props: Props) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const canEdit = can('subscriptions.plans.create', 'subscriptions.plans.update');
  const f = (field: string) => t(`subscriptions.plans.fields.${field}`);
  const url = useSuiteUrl();
  const branchName = (id: number) => props.branches.find((branch) => branch.value === id)?.label;

  return (
    <SuiteLayout title={t('subscriptions.plans.title')}>
      <PageHeader title={t('subscriptions.plans.title')} actions={canEdit && <PlanDialog {...props} />} />
      <DataTable<Plan>
        rows={props.plans}
        empty={t('subscriptions.plans.none')}
        columns={[
          { key: 'name', label: f('name_ar'), render: (plan) => <span className={plan.is_active ? undefined : 'text-muted-foreground line-through'}><span className="block font-medium">{plan.name_ar}</span><bdi className="text-xs text-muted-foreground">{plan.name_en}</bdi></span> },
          { key: 'period', label: f('period'), render: (plan) => `${plan.period_count} ${t(`subscriptions.period_unit.${plan.period_unit}`)}` },
          { key: 'price', label: f('price'), render: (plan) => <span className="tabular-nums">{formatMoney(plan.price, locale)}</span> },
          { key: 'sessions', label: f('sessions'), render: (plan) => plan.sessions ?? t('subscriptions.plans.unlimited') },
          { key: 'gender', label: f('gender'), render: (plan) => t(`subscriptions.plan_gender.${plan.gender}`) },
          { key: 'branches', label: t('subscriptions.members.fields.branch'), render: (plan) => (plan.branch_ids.length ? plan.branch_ids.map(branchName).join('، ') : t('subscriptions.plans.all_branches')) },
          { key: 'actions', label: '', className: 'text-end', render: (plan) => (
            <span className="flex items-center justify-end gap-1">
              {plan.is_corporate && <Badge variant="outline">{t('subscriptions.corporate.badge')}</Badge>}
              {plan.family_size > 1 && <Badge variant="outline">{t('subscriptions.plans.family_badge', { size: String(plan.family_size) })}</Badge>}
              {canEdit && plan.is_corporate && <ConfirmAction href={url(`/subscriptions/plans/${plan.id}/sync`)} method="post" size="sm" variant="outline" title={t('subscriptions.corporate.sync')}>{t('subscriptions.corporate.sync')}</ConfirmAction>}
              {canEdit && <PlanDialog plan={plan} {...props} />}
            </span>
          ) },
        ]}
      />
      <ZonesCard zones={props.zones} branches={props.branches} genders={props.genders} canEdit={canEdit} />
    </SuiteLayout>
  );
}

/** Women-only and men-only areas checked at the door (S2). */
function ZonesCard({ zones, branches, genders, canEdit }: { zones: ZoneRow[]; branches: ScopedOption[]; genders: Option[]; canEdit: boolean }) {
  const t = useT();
  const url = useSuiteUrl();
  const z = (key: string) => t(`subscriptions.zones.${key}`);
  const form = useForm({ branch_id: String(branches[0]?.value ?? ''), name_ar: '', name_en: '', gender: 'female' });

  return (
    <Card className="mt-6">
      <CardHeader><CardTitle className="text-base">{z('title')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        <p className="text-muted-foreground">{z('hint')}</p>
        <ul className="divide-y">
          {zones.map((zone) => (
            <li key={zone.id} className="flex items-center justify-between py-2">
              <span>{zone.name} · <span className="text-muted-foreground">{branches.find((branch) => Number(branch.value) === zone.branch_id)?.label} · {t(`subscriptions.plan_gender.${zone.gender}`)}</span></span>
              {canEdit && <ConfirmAction href={url(`/subscriptions/zones/${zone.id}`)} method="delete" size="icon-sm" variant="ghost" title={t('common.actions.remove')}><Trash2 /></ConfirmAction>}
            </li>
          ))}
        </ul>
        {canEdit && (
          <form onSubmit={(event) => { event.preventDefault(); form.post(url('/subscriptions/zones'), { preserveScroll: true, onSuccess: () => form.reset('name_ar', 'name_en') }); }} className="grid gap-2 sm:grid-cols-5 sm:items-end">
            <FormField label={t('subscriptions.members.fields.branch')}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>
            <FormField label={t('subscriptions.plans.fields.name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
            <FormField label={t('subscriptions.plans.fields.name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
            <FormField label={t('subscriptions.plans.fields.gender')}><SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} options={genders} /></FormField>
            <Button type="submit" size="sm" disabled={form.processing}>{t('common.actions.add_row')}</Button>
          </form>
        )}
      </CardContent>
    </Card>
  );
}
