import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import FieldSchemaEditor from '@/components/hr/FieldSchemaEditor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { CatalogItem, DocumentField } from '@/types/hr';
import type { Option } from '@/types/resources';

type Catalog = 'sections' | 'jobs' | 'allowance-types' | 'document-types' | 'bonus-types' | 'deduction-types' | 'leave-types';

interface Props {
  sections: CatalogItem[];
  jobs: CatalogItem[];
  allowance_types: CatalogItem[];
  document_types: CatalogItem[];
  bonus_types: CatalogItem[];
  deduction_types: CatalogItem[];
  leave_types: CatalogItem[];
  companies: Option[];
  roles: Option[];
  settings: HrRules;
  calculations: Option[];
  field_types: Option[];
  leave_accruals: Option[];
  leave_year_bases: Option[];
}

function CheckList({ options, value, onChange }: { options: Option[]; value: number[]; onChange: (value: number[]) => void }) {
  return (
    <div className="flex flex-wrap gap-3">
      {options.map((option) => (
        <label key={option.value} className="flex items-center gap-2 text-sm">
          <Checkbox checked={value.includes(Number(option.value))} onCheckedChange={(state) => onChange(state === true ? [...value, Number(option.value)] : value.filter((id) => id !== Number(option.value)))} />
          {option.label}
        </label>
      ))}
    </div>
  );
}

function ItemDialog({ catalog, item, sections, companies, roles, calculations, fieldTypes, leaveAccruals, leaveYearBases }: { catalog: Catalog; item?: CatalogItem; sections: CatalogItem[]; companies: Option[]; roles: Option[]; calculations: Option[]; fieldTypes: Option[]; leaveAccruals: Option[]; leaveYearBases: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`hr.catalog.fields.${field}`);
  const form = useForm<{ name_ar: string; name_en: string; is_active: boolean; sort_order: number; section_ids: number[]; blocked_company_ids: number[]; default_role_id: string; calculation: string; default_value: string; counts_for_gosi: boolean; has_number: boolean; has_expiry: boolean; alert_days: string; renewal_cost: string; fields: DocumentField[]; days_per_year: string; senior_days_per_year: string; senior_after_years: string; accrual: string; year_basis: string; carry_over_days: string; allow_negative: boolean; is_paid: boolean; needs_balance: boolean }>({
    name_ar: item?.name_ar ?? '',
    name_en: item?.name_en ?? '',
    is_active: item?.is_active ?? true,
    sort_order: item?.sort_order ?? 0,
    section_ids: item?.section_ids ?? [],
    blocked_company_ids: item?.blocked_company_ids ?? [],
    default_role_id: item?.default_role_id ? String(item.default_role_id) : '',
    calculation: item?.calculation ?? 'fixed',
    default_value: item?.default_value ?? '0',
    counts_for_gosi: item?.counts_for_gosi ?? false,
    has_number: item?.has_number ?? true,
    has_expiry: item?.has_expiry ?? true,
    alert_days: (item?.alert_days ?? [90, 30, 7]).join(', '),
    renewal_cost: item?.renewal_cost ? String(item.renewal_cost) : '',
    fields: item?.fields ?? [],
    days_per_year: String(item?.days_per_year ?? '21'),
    senior_days_per_year: item ? String(item.senior_days_per_year ?? '') : '30',
    senior_after_years: item ? String(item.senior_after_years ?? '') : '5',
    accrual: String(item?.accrual ?? 'monthly'),
    year_basis: String(item?.year_basis ?? 'calendar'),
    carry_over_days: String(item?.carry_over_days ?? ''),
    allow_negative: (item?.allow_negative as boolean | undefined) ?? false,
    is_paid: (item?.is_paid as boolean | undefined) ?? true,
    needs_balance: (item?.needs_balance as boolean | undefined) ?? true,
  });
  const { data, setData, errors } = form;
  const titleKey = { sections: 'new_section', jobs: 'new_job', 'allowance-types': 'new_allowance_type', 'document-types': 'new_document_type', 'bonus-types': 'new_bonus_type', 'deduction-types': 'new_deduction_type', 'leave-types': 'new_leave_type' }[catalog];

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    form.transform((payload) => ({
      ...payload,
      alert_days: payload.alert_days.split(',').map((value) => Number(value.trim())).filter(Boolean),
      default_role_id: payload.default_role_id === '' ? null : Number(payload.default_role_id),
      renewal_cost: payload.renewal_cost === '' ? null : payload.renewal_cost,
      senior_days_per_year: payload.senior_days_per_year || null, senior_after_years: payload.senior_after_years || null, carry_over_days: payload.carry_over_days === '' ? null : payload.carry_over_days,
    }));
    item ? form.put(url(`/hr/catalog/${catalog}/${item.id}`), options) : form.post(url(`/hr/catalog/${catalog}`), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {item ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t(`hr.catalog.${titleKey}`)}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{item ? item.name : t(`hr.catalog.${titleKey}`)}</DialogTitle></DialogHeader>
          <FormField label={f('name_ar')} error={errors.name_ar}><Input dir="rtl" value={data.name_ar} onChange={(event) => setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={errors.name_en}><Input dir="ltr" value={data.name_en} onChange={(event) => setData('name_en', event.target.value)} /></FormField>
          {catalog === 'jobs' && (
            <FormField label={t('hr.catalog.linked_sections')} error={errors.section_ids} className="sm:col-span-2">
              <CheckList options={sections.map((section) => ({ value: section.id, label: section.name }))} value={data.section_ids} onChange={(value) => setData('section_ids', value)} />
            </FormField>
          )}
          {catalog === 'jobs' && (
            <FormField label={t('hr.catalog.default_role')} hint={t('hr.catalog.default_role_hint')} error={errors.default_role_id} className="sm:col-span-2">
              <SelectInput value={data.default_role_id} onChange={(value) => setData('default_role_id', value)} options={roles} emptyLabel={t('hr.catalog.no_role')} />
            </FormField>
          )}
          {(catalog === 'sections' || catalog === 'jobs') && companies.length > 1 && (
            <FormField label={t('hr.catalog.blocked_in')} hint={t('hr.catalog.blocked_hint')} className="sm:col-span-2">
              <CheckList options={companies} value={data.blocked_company_ids} onChange={(value) => setData('blocked_company_ids', value)} />
            </FormField>
          )}
          {catalog === 'allowance-types' && (
            <>
              <FormField label={f('calculation')} error={errors.calculation}><SelectInput value={data.calculation} onChange={(value) => setData('calculation', value)} options={calculations} /></FormField>
              <FormField label={f('default_value')} error={errors.default_value}><Input type="number" step="0.01" dir="ltr" value={data.default_value} onChange={(event) => setData('default_value', event.target.value)} /></FormField>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.counts_for_gosi} onCheckedChange={(state) => setData('counts_for_gosi', state === true)} />{f('counts_for_gosi')}</label>
            </>
          )}
          {catalog === 'leave-types' && (
            <>
              <FormField label={t('hr.leave.fields.days_per_year')} error={errors.days_per_year}><Input type="number" step="0.5" dir="ltr" value={data.days_per_year} onChange={(event) => setData('days_per_year', event.target.value)} /></FormField>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.is_paid} onCheckedChange={(state) => setData('is_paid', state === true)} />{t('hr.leave.fields.is_paid')}</label>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.needs_balance} onCheckedChange={(state) => setData('needs_balance', state === true)} />{t('hr.leave.fields.needs_balance')}</label>
              {data.needs_balance && (
                <>
                  <FormField label={t('hr.leave.fields.senior_days_per_year')} error={errors.senior_days_per_year}><Input type="number" step="0.5" dir="ltr" value={data.senior_days_per_year} onChange={(event) => setData('senior_days_per_year', event.target.value)} /></FormField>
                  <FormField label={t('hr.leave.fields.senior_after_years')} error={errors.senior_after_years}><Input type="number" dir="ltr" value={data.senior_after_years} onChange={(event) => setData('senior_after_years', event.target.value)} /></FormField>
                  <FormField label={t('hr.leave.fields.accrual')} hint={t(`hr.leave.accrual_hint.${data.accrual}`)} error={errors.accrual}><SelectInput value={data.accrual} onChange={(value) => setData('accrual', value)} options={leaveAccruals} /></FormField>
                  <FormField label={t('hr.leave.fields.year_basis')} error={errors.year_basis}><SelectInput value={data.year_basis} onChange={(value) => setData('year_basis', value)} options={leaveYearBases} /></FormField>
                  <FormField label={t('hr.leave.fields.carry_over_days')} hint={t('hr.leave.carry_hint')} error={errors.carry_over_days}><Input type="number" step="0.5" dir="ltr" value={data.carry_over_days} onChange={(event) => setData('carry_over_days', event.target.value)} /></FormField>
                  <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.allow_negative} onCheckedChange={(state) => setData('allow_negative', state === true)} />{t('hr.leave.fields.allow_negative')}</label>
                </>
              )}
            </>
          )}
          {catalog === 'document-types' && (
            <>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.has_number} onCheckedChange={(state) => setData('has_number', state === true)} />{f('has_number')}</label>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.has_expiry} onCheckedChange={(state) => setData('has_expiry', state === true)} />{f('has_expiry')}</label>
              {data.has_expiry && <FormField label={f('alert_days')} error={errors.alert_days}><Input dir="ltr" value={data.alert_days} onChange={(event) => setData('alert_days', event.target.value)} placeholder="90, 30, 7" /></FormField>}
              {data.has_expiry && <FormField label={f('renewal_cost')} error={errors.renewal_cost}><Input type="number" step="0.01" min="0" dir="ltr" value={data.renewal_cost} onChange={(event) => setData('renewal_cost', event.target.value)} /></FormField>}
              <FormField label={f('fields')} className="sm:col-span-2">
                <FieldSchemaEditor fields={data.fields} onChange={(fields) => setData('fields', fields)} types={fieldTypes} errors={errors as Record<string, string>} />
              </FormField>
            </>
          )}
          <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.is_active} onCheckedChange={(state) => setData('is_active', state === true)} />{f('is_active')}</label>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ItemList({ catalog, items, ...rest }: { catalog: Catalog; items: CatalogItem[]; sections: CatalogItem[]; companies: Option[]; roles: Option[]; calculations: Option[]; fieldTypes: Option[]; leaveAccruals: Option[]; leaveYearBases: Option[] }) {
  const t = useT();
  const { can } = useAccess();
  const companyName = (id: number) => rest.companies.find((company) => Number(company.value) === id)?.label ?? id;

  return (
    <>
      <div className="mb-3 flex justify-end">{can('hr.catalog.create') && <ItemDialog catalog={catalog} {...rest} />}</div>
      <Card><CardContent>
        <ul className="divide-y">
          {items.length === 0 && <li className="py-6 text-center text-sm text-muted-foreground">{t('common.actions.empty')}</li>}
          {items.map((item) => (
            <li key={item.id} className="flex items-center justify-between gap-3 py-3 text-sm">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium">{item.name_ar}</span><bdi className="text-muted-foreground">{item.name_en}</bdi>
                  {!item.is_active && <Badge variant="outline" className="text-muted-foreground">—</Badge>}
                </div>
                <div className="mt-1 flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                  {item.sections?.map((section) => <Badge key={section} variant="outline">{section}</Badge>)}
                  {item.blocked_company_ids?.map((id) => <Badge key={id} variant="outline" className="border-transparent bg-red-100 text-red-800">{t('hr.catalog.blocked_in')} {companyName(id)}</Badge>)}
                  {item.calculation && <span>{t(`hr.allowance_calculation.${item.calculation}`)} · {item.default_value}</span>}
                  {item.fields && item.fields.length > 0 && <span>{item.fields.length} {t('hr.catalog.fields.fields').toLowerCase()}</span>}
                  {catalog === 'leave-types' && Boolean(item.needs_balance) && <span>{t('hr.leave.rule_summary', { days: String(item.days_per_year), senior: String(item.senior_days_per_year ?? item.days_per_year), years: String(item.senior_after_years ?? '—'), accrual: t(`hr.leave_accrual.${item.accrual}`) })}</span>}
                </div>
              </div>
              {can('hr.catalog.update') && <ItemDialog catalog={catalog} item={item} {...rest} />}
            </li>
          ))}
        </ul>
      </CardContent></Card>
    </>
  );
}

export interface HrRules {
  dual_control: boolean;
  [key: string]: unknown;
}

/** Tenant-wide HR rules, switched on and off here. */
function RulesCard({ settings }: { settings: HrRules }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const form = useForm({ dual_control: settings.dual_control });

  return (
    <Card><CardContent>
      <form onSubmit={(event) => { event.preventDefault(); form.put(url('/hr/settings'), { preserveScroll: true }); }} className="flex flex-col gap-4">
        <label className="flex items-start gap-2 text-sm">
          <Checkbox checked={form.data.dual_control} disabled={!can('hr.catalog.update')} onCheckedChange={(state) => form.setData('dual_control', state === true)} />
          <span><span className="font-medium">{t('hr.changes.dual_control')}</span><span className="block text-muted-foreground">{t('hr.changes.dual_control_hint')}</span></span>
        </label>
        {can('hr.catalog.update') && <div className="flex justify-end"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>}
      </form>
    </CardContent></Card>
  );
}

export default function CatalogIndex({ sections, jobs, allowance_types: allowanceTypes, document_types: documentTypes, bonus_types: bonusTypes, deduction_types: deductionTypes, leave_types: leaveTypes, companies, roles, settings, calculations, field_types: fieldTypes, leave_accruals: leaveAccruals, leave_year_bases: leaveYearBases }: Props) {
  const t = useT();
  const shared = { sections, companies, roles, calculations, fieldTypes, leaveAccruals, leaveYearBases };

  return (
    <SuiteLayout title={t('hr.catalog.title')}>
      <PageHeader title={t('hr.catalog.title')} />
      <Tabs defaultValue="sections">
        <TabsList>
          <TabsTrigger value="sections">{t('hr.catalog.sections')}</TabsTrigger>
          <TabsTrigger value="jobs">{t('hr.catalog.jobs')}</TabsTrigger>
          <TabsTrigger value="allowance-types">{t('hr.catalog.allowance_types')}</TabsTrigger>
          <TabsTrigger value="document-types">{t('hr.catalog.document_types')}</TabsTrigger>
          <TabsTrigger value="bonus-types">{t('hr.pay.bonuses')}</TabsTrigger>
          <TabsTrigger value="deduction-types">{t('hr.pay.deductions')}</TabsTrigger>
          <TabsTrigger value="leave-types">{t('hr.leave.types')}</TabsTrigger>
          <TabsTrigger value="rules">{t('hr.changes.rules')}</TabsTrigger>
        </TabsList>
        <TabsContent value="sections"><ItemList catalog="sections" items={sections} {...shared} /></TabsContent>
        <TabsContent value="jobs"><ItemList catalog="jobs" items={jobs} {...shared} /></TabsContent>
        <TabsContent value="allowance-types"><ItemList catalog="allowance-types" items={allowanceTypes} {...shared} /></TabsContent>
        <TabsContent value="document-types"><ItemList catalog="document-types" items={documentTypes} {...shared} /></TabsContent>
        <TabsContent value="bonus-types"><ItemList catalog="bonus-types" items={bonusTypes} {...shared} /></TabsContent>
        <TabsContent value="deduction-types"><ItemList catalog="deduction-types" items={deductionTypes} {...shared} /></TabsContent>
        <TabsContent value="leave-types"><ItemList catalog="leave-types" items={leaveTypes} {...shared} /></TabsContent>
        <TabsContent value="rules"><RulesCard settings={settings} /></TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
