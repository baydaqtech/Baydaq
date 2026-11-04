import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';
import type { CompanyResource, OrganizationResource, PointsTier } from '@/types/suite';

interface Props {
  company: CompanyResource | null;
  organizations: OrganizationResource[];
  timezones: Option[];
}

/** Up to five tiers: a name in both languages, the points ever earned to reach it, and its multiplier. */
function PointsTiersEditor({ tiers, onChange, errors }: { tiers: PointsTier[]; onChange: (tiers: PointsTier[]) => void; errors: Record<string, string> }) {
  const t = useT();
  const f = (field: string) => t(`suite.structure.tiers.${field}`);
  const update = (index: number, patch: Partial<PointsTier>) => onChange(tiers.map((tier, at) => (at === index ? { ...tier, ...patch } : tier)));

  return (
    <div className="flex flex-col gap-2 sm:col-span-3">
      <div className="flex items-center justify-between">
        <span className="text-sm">{f('title')}</span>
        {tiers.length < 5 && (
          <Button type="button" size="sm" variant="ghost" onClick={() => onChange([...tiers, { name_ar: '', name_en: '', from: (tiers.at(-1)?.from ?? 0) + 1000, multiplier: 1.25 }])}>
            <Plus data-icon="inline-start" />{f('add')}
          </Button>
        )}
      </div>
      {tiers.length === 0 && <p className="text-xs text-muted-foreground">{f('none')}</p>}
      {tiers.map((tier, index) => (
        <div key={index} className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_7rem_6rem_auto]">
          <Input aria-label={f('name_ar')} placeholder={f('name_ar')} dir="rtl" value={tier.name_ar} onChange={(event) => update(index, { name_ar: event.target.value })} aria-invalid={!!errors[`points_tiers.${index}.name_ar`]} />
          <Input aria-label={f('name_en')} placeholder={f('name_en')} dir="ltr" value={tier.name_en} onChange={(event) => update(index, { name_en: event.target.value })} aria-invalid={!!errors[`points_tiers.${index}.name_en`]} />
          <Input aria-label={f('from')} placeholder={f('from')} type="number" min="1" dir="ltr" value={tier.from} onChange={(event) => update(index, { from: Number(event.target.value) })} aria-invalid={!!errors[`points_tiers.${index}.from`]} />
          <Input aria-label={f('multiplier')} placeholder={f('multiplier')} type="number" min="1" max="5" step="0.05" dir="ltr" value={tier.multiplier} onChange={(event) => update(index, { multiplier: Number(event.target.value) })} aria-invalid={!!errors[`points_tiers.${index}.multiplier`]} />
          <Button type="button" size="icon" variant="ghost" aria-label={f('remove')} onClick={() => onChange(tiers.filter((_, at) => at !== index))}><Trash2 /></Button>
        </div>
      ))}
    </div>
  );
}

export default function CompanyForm({ company, organizations, timezones }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const f = (field: string) => t(`suite.structure.fields.${field}`);
  const form = useForm({
    organization_id: company?.organization_id ?? organizations[0]?.id ?? '',
    name_ar: company?.name_ar ?? '',
    name_en: company?.name_en ?? '',
    slug: company?.slug ?? '',
    cr_number: company?.cr_number ?? '',
    vat_number: company?.vat_number ?? '',
    unified_number: company?.unified_number ?? '',
    gosi_number: company?.gosi_number ?? '',
    qiwa_id: company?.qiwa_id ?? '',
    street: company?.street ?? '',
    building_number: company?.building_number ?? '',
    district: company?.district ?? '',
    city: company?.city ?? '',
    postal_code: company?.postal_code ?? '',
    timezone: company?.timezone ?? 'Asia/Riyadh',
    loyalty_percent: company?.loyalty_percent ?? '',
    points_per_riyal: company?.points_per_riyal ?? '',
    point_value: company?.point_value ?? '',
    points_tiers: company?.points_tiers ?? [],
    client_scope: company?.client_scope ?? 'company',
    status: company?.status ?? 'active',
  });
  const { data, setData, errors, processing } = form;
  type Field = keyof typeof data;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    company ? form.put(url(`/structure/companies/${company.id}`)) : form.post(url('/structure/companies'));
  }

  const text = (field: Field, props: React.ComponentProps<typeof Input> = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <Input id={field} value={String(data[field] ?? '')} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );

  return (
    <SuiteLayout title={company ? t('suite.structure.edit_company') : t('suite.structure.new_company')}>
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <FormField label={f('organization')} htmlFor="organization_id" error={errors.organization_id} className="sm:col-span-2">
              <SelectInput
                id="organization_id"
                value={data.organization_id}
                onChange={(value) => setData('organization_id', Number(value))}
                options={organizations.map((organization) => ({ value: organization.id, label: organization.name }))}
              />
            </FormField>
            {text('name_ar', { dir: 'rtl' })}
            {text('name_en', { dir: 'ltr' })}
            {text('slug', { dir: 'ltr' })}
            {text('cr_number', { dir: 'ltr', inputMode: 'numeric' })}
            {text('vat_number', { dir: 'ltr', inputMode: 'numeric' })}
            {text('unified_number', { dir: 'ltr', inputMode: 'numeric' })}
            {text('gosi_number', { dir: 'ltr' })}
            {text('qiwa_id', { dir: 'ltr' })}
            {text('street')}
            {text('building_number', { dir: 'ltr', inputMode: 'numeric', maxLength: 4 })}
            {text('district')}
            {text('city')}
            {text('postal_code', { dir: 'ltr', inputMode: 'numeric', maxLength: 5 })}
            <FormField label={f('timezone')} htmlFor="timezone" error={errors.timezone}>
              <SelectInput id="timezone" value={data.timezone} onChange={(value) => setData('timezone', value)} options={timezones} />
            </FormField>
            <fieldset className="grid gap-4 rounded-lg border p-3 sm:col-span-2 sm:grid-cols-3">
              <legend className="px-1 text-sm font-medium">{t('suite.structure.loyalty')}</legend>
              {text('loyalty_percent', { dir: 'ltr', inputMode: 'decimal' })}
              {text('points_per_riyal', { dir: 'ltr', inputMode: 'decimal' })}
              {text('point_value', { dir: 'ltr', inputMode: 'decimal' })}
              <PointsTiersEditor tiers={data.points_tiers} onChange={(tiers) => setData('points_tiers', tiers)} errors={errors as Record<string, string>} />
            </fieldset>
            <FormField label={f('client_scope')} htmlFor="client_scope" error={errors.client_scope}>
              <SelectInput id="client_scope" value={data.client_scope} onChange={(value) => setData('client_scope', value)} options={['branch', 'company', 'organization'].map((value) => ({ value, label: t(`suite.customers.scopes.${value}`) }))} />
            </FormField>
            <FormField label={f('status')} htmlFor="status" error={errors.status}>
              <SelectInput
                id="status"
                value={data.status}
                onChange={(value) => setData('status', value)}
                options={[{ value: 'active', label: t('suite.structure.status_active') }, { value: 'inactive', label: t('suite.structure.status_inactive') }]}
              />
            </FormField>
            <div className="sm:col-span-2">
              <FormActions processing={processing} cancelHref={url('/structure')} />
            </div>
          </form>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
