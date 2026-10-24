import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Copy, Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useCopy, useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';
import type { Option } from '@/types/resources';

interface Service {
  id: number; company_id: number; name: string; name_ar: string; name_en: string; description_ar: string | null; description_en: string | null;
  duration_minutes: number; buffer_minutes: number; price: string; vat_rate: string; deposit: string; needs_staff: boolean; is_online: boolean; is_active: boolean;
  color: string | null; sort_order: number; resource_ids: number[]; employee_ids: number[]; [key: string]: unknown;
}
interface ResourceRow { id: number; company_id: number; branch_id: number; name: string; name_ar: string; name_en: string; kind: string; capacity: number; is_active: boolean; sort_order: number; [key: string]: unknown }
type Hours = Record<string, [string, string][]>;
interface Settings { branch_id: number; branch: string; hours: Hours; slot_minutes: number; min_notice_minutes: number; max_days_ahead: number; is_online: boolean; booking_url: string }

interface Props {
  companies: Option[];
  branches: ScopedOption[];
  services: Service[];
  resources: ResourceRow[];
  settings: Settings[];
  staff: ScopedOption[];
  kinds: Option[];
}

const SLOT_STEPS: Option[] = [5, 10, 15, 20, 30, 45, 60, 90, 120].map((value) => ({ value, label: String(value) }));

function useField() {
  const t = useT();
  return (field: string) => t(`reservations.catalog.fields.${field}`);
}

function EditTrigger({ editing, label }: { editing: boolean; label: string }) {
  const t = useT();
  return editing ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{label}</Button>;
}

function ServiceDialog({ service, companies, resources, staff, branches }: { service?: Service; companies: Option[]; resources: ResourceRow[]; staff: ScopedOption[]; branches: ScopedOption[] }) {
  const t = useT();
  const f = useField();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({
    company_id: service?.company_id ?? companies[0]?.value ?? '', name_ar: service?.name_ar ?? '', name_en: service?.name_en ?? '',
    description_ar: service?.description_ar ?? '', description_en: service?.description_en ?? '',
    duration_minutes: service?.duration_minutes ?? 30, buffer_minutes: service?.buffer_minutes ?? 0, price: service?.price ?? '', vat_rate: service ? String(Number(service.vat_rate)) : '15',
    deposit: service?.deposit ?? '0', needs_staff: service?.needs_staff ?? false, is_online: service?.is_online ?? true, is_active: service?.is_active ?? true,
    color: service?.color ?? '#c8a84b', resource_ids: service?.resource_ids ?? ([] as number[]), employee_ids: service?.employee_ids ?? ([] as number[]),
  });
  const companyId = Number(form.data.company_id);
  const branchName = (id: number) => branches.find((branch) => branch.value === id)?.label;

  function toggle(field: 'resource_ids' | 'employee_ids', id: number, checked: boolean) {
    form.setData(field, checked ? [...form.data[field], id] : form.data[field].filter((value) => value !== id));
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    service ? form.put(url(`/reservations/catalog/services/${service.id}`), options) : form.post(url('/reservations/catalog/services'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><EditTrigger editing={!!service} label={t('reservations.catalog.new_service')} /></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-3xl overflow-y-auto">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{service ? service.name : t('reservations.catalog.new_service')}</DialogTitle></DialogHeader>
          {!service && companies.length > 1 && <FormField className="sm:col-span-2" label={f('company')}><SelectInput value={form.data.company_id} onChange={(value) => form.setData((data) => ({ ...data, company_id: Number(value), resource_ids: [], employee_ids: [] }))} options={companies} /></FormField>}
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('description_ar')}><Textarea rows={2} value={form.data.description_ar} onChange={(event) => form.setData('description_ar', event.target.value)} /></FormField>
          <FormField label={f('description_en')}><Textarea dir="ltr" rows={2} value={form.data.description_en} onChange={(event) => form.setData('description_en', event.target.value)} /></FormField>
          <FormField label={f('duration_minutes')} error={form.errors.duration_minutes}><Input dir="ltr" type="number" min={5} value={form.data.duration_minutes} onChange={(event) => form.setData('duration_minutes', Number(event.target.value))} /></FormField>
          <FormField label={f('buffer_minutes')} error={form.errors.buffer_minutes}><Input dir="ltr" type="number" min={0} value={form.data.buffer_minutes} onChange={(event) => form.setData('buffer_minutes', Number(event.target.value))} /></FormField>
          <FormField label={f('price')} error={form.errors.price}><Input dir="ltr" inputMode="decimal" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} /></FormField>
          <FormField label={f('vat_rate')} error={form.errors.vat_rate}><SelectInput value={form.data.vat_rate} onChange={(value) => form.setData('vat_rate', value)} options={[{ value: '15', label: '15%' }, { value: '5', label: '5%' }, { value: '0', label: '0%' }]} /></FormField>
          <FormField label={f('deposit')} error={form.errors.deposit}><Input dir="ltr" inputMode="decimal" value={form.data.deposit} onChange={(event) => form.setData('deposit', event.target.value)} /></FormField>
          <FormField label={f('color')}><Input type="color" value={form.data.color} onChange={(event) => form.setData('color', event.target.value)} /></FormField>
          <div className="flex flex-wrap gap-4 sm:col-span-2">
            <SwitchField id="service-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
            <SwitchField id="service-online" label={f('is_online')} checked={form.data.is_online} onChange={(value: boolean) => form.setData('is_online', value)} />
            <SwitchField id="service-staff" label={f('needs_staff')} checked={form.data.needs_staff} onChange={(value: boolean) => form.setData('needs_staff', value)} />
          </div>
          <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{f('resources')}</legend>
            <div className="flex flex-wrap gap-3">
              {resources.filter((resource) => resource.company_id === companyId).map((resource) => (
                <label key={resource.id} className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.resource_ids.includes(resource.id)} onCheckedChange={(checked) => toggle('resource_ids', resource.id, checked === true)} />{resource.name} <span className="text-xs text-muted-foreground">{branchName(resource.branch_id)}</span></label>
              ))}
            </div>
            {form.errors.resource_ids && <p className="text-sm text-destructive">{form.errors.resource_ids}</p>}
          </fieldset>
          {form.data.needs_staff && (
            <fieldset className="flex flex-col gap-2 sm:col-span-2">
              <legend className="mb-1 text-sm font-medium">{f('staff')}</legend>
              <p className="text-xs text-muted-foreground">{t('reservations.catalog.staff_hint')}</p>
              <div className="flex flex-wrap gap-3">
                {staff.filter((employee) => employee.company_id === companyId).map((employee) => (
                  <label key={employee.value} className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.employee_ids.includes(Number(employee.value))} onCheckedChange={(checked) => toggle('employee_ids', Number(employee.value), checked === true)} />{employee.label}</label>
                ))}
              </div>
              {form.errors.employee_ids && <p className="text-sm text-destructive">{form.errors.employee_ids}</p>}
            </fieldset>
          )}
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ResourceDialog({ resource, branches, kinds }: { resource?: ResourceRow; branches: ScopedOption[]; kinds: Option[] }) {
  const t = useT();
  const f = useField();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ branch_id: resource?.branch_id ?? branches[0]?.value ?? '', name_ar: resource?.name_ar ?? '', name_en: resource?.name_en ?? '', kind: resource?.kind ?? 'room', capacity: resource?.capacity ?? 1, is_active: resource?.is_active ?? true });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    resource ? form.put(url(`/reservations/catalog/resources/${resource.id}`), options) : form.post(url('/reservations/catalog/resources'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><EditTrigger editing={!!resource} label={t('reservations.catalog.new_resource')} /></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{resource ? resource.name : t('reservations.catalog.new_resource')}</DialogTitle></DialogHeader>
          {!resource && <FormField className="sm:col-span-2" label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', Number(value))} options={branches} /></FormField>}
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('kind')}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds} /></FormField>
          <FormField label={f('capacity')} error={form.errors.capacity}><Input dir="ltr" type="number" min={1} value={form.data.capacity} onChange={(event) => form.setData('capacity', Number(event.target.value))} /></FormField>
          <SwitchField id="resource-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function SettingsCard({ settings }: { settings: Settings }) {
  const t = useT();
  const f = useField();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const weekdays = useCopy<string[]>('reservations.catalog.weekdays');
  const form = useForm({ hours: settings.hours, slot_minutes: settings.slot_minutes, min_notice_minutes: settings.min_notice_minutes, max_days_ahead: settings.max_days_ahead, is_online: settings.is_online });

  function setPeriods(day: number, periods: [string, string][]) {
    form.setData('hours', { ...form.data.hours, [String(day)]: periods });
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{settings.branch}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div className="flex flex-col gap-2">
          {weekdays.map((name, day) => {
            const periods = form.data.hours[String(day)] ?? [];
            return (
              <div key={day} className="grid grid-cols-[6rem_1fr_auto] items-center gap-2 text-sm">
                <span>{name}</span>
                <div className="flex flex-wrap gap-2">
                  {periods.length === 0 && <span className="text-muted-foreground">{t('reservations.catalog.closed')}</span>}
                  {periods.map(([opens, closes], index) => (
                    <span key={index} className="flex items-center gap-1" dir="ltr">
                      <Input type="time" className="w-28" value={opens} onChange={(event) => setPeriods(day, periods.map((period, i) => (i === index ? [event.target.value, period[1]] : period)))} />
                      –
                      <Input type="time" className="w-28" value={closes} onChange={(event) => setPeriods(day, periods.map((period, i) => (i === index ? [period[0], event.target.value] : period)))} />
                      <Button type="button" size="icon-xs" variant="ghost" onClick={() => setPeriods(day, periods.filter((_, i) => i !== index))} aria-label={t('common.actions.delete')}><Trash2 /></Button>
                    </span>
                  ))}
                </div>
                <Button type="button" size="xs" variant="outline" onClick={() => setPeriods(day, [...periods, ['09:00', '17:00']])}><Plus data-icon="inline-start" />{t('reservations.catalog.add_period')}</Button>
              </div>
            );
          })}
          {form.errors.hours && <p className="text-sm text-destructive">{form.errors.hours}</p>}
        </div>
        <div className="grid gap-3 sm:grid-cols-3">
          <FormField label={f('slot_minutes')}><SelectInput value={form.data.slot_minutes} onChange={(value) => form.setData('slot_minutes', Number(value))} options={SLOT_STEPS} /></FormField>
          <FormField label={f('min_notice_minutes')} error={form.errors.min_notice_minutes}><Input dir="ltr" type="number" min={0} value={form.data.min_notice_minutes} onChange={(event) => form.setData('min_notice_minutes', Number(event.target.value))} /></FormField>
          <FormField label={f('max_days_ahead')} error={form.errors.max_days_ahead}><Input dir="ltr" type="number" min={1} value={form.data.max_days_ahead} onChange={(event) => form.setData('max_days_ahead', Number(event.target.value))} /></FormField>
        </div>
        <SwitchField id={`online-${settings.branch_id}`} label={f('online')} checked={form.data.is_online} onChange={(value: boolean) => form.setData('is_online', value)} />
      </CardContent>
      <CardFooter className="flex flex-wrap justify-between gap-2">
        <Button type="button" size="sm" variant="ghost" disabled={!form.data.is_online} onClick={() => navigator.clipboard?.writeText(settings.booking_url).then(() => toast.success(t('reservations.catalog.copied')))}><Copy data-icon="inline-start" />{t('reservations.catalog.copy_link')}</Button>
        {can('reservations.catalog.update') && <Button size="sm" disabled={form.processing} onClick={() => form.put(url(`/reservations/catalog/branches/${settings.branch_id}`), { preserveScroll: true })}>{t('common.actions.save')}</Button>}
      </CardFooter>
    </Card>
  );
}

export default function Catalog({ companies, branches, services, resources, settings, staff, kinds }: Props) {
  const t = useT();
  const f = useField();
  const { locale } = useLocale();
  const { can } = useAccess();
  const canEdit = can('reservations.catalog.create', 'reservations.catalog.update');
  const branchName = (id: number) => branches.find((branch) => branch.value === id)?.label;

  return (
    <SuiteLayout title={t('reservations.catalog.title')}>
      <PageHeader title={t('reservations.catalog.title')} />
      <Tabs defaultValue="services">
        <TabsList>
          <TabsTrigger value="services">{t('reservations.catalog.services')}</TabsTrigger>
          <TabsTrigger value="resources">{t('reservations.catalog.resources')}</TabsTrigger>
          <TabsTrigger value="hours">{t('reservations.catalog.hours')}</TabsTrigger>
        </TabsList>

        <TabsContent value="services" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><ServiceDialog companies={companies} resources={resources} staff={staff} branches={branches} /></div>}
          <DataTable<Service>
            rows={services}
            empty={t('reservations.catalog.none')}
            columns={[
              { key: 'name', label: f('name_ar'), render: (service) => <span className={service.is_active ? 'flex items-center gap-2' : 'flex items-center gap-2 text-muted-foreground line-through'}>{service.color && <span className="size-3 rounded-full" style={{ backgroundColor: service.color }} />}<span><span className="block font-medium">{service.name_ar}</span><bdi className="text-xs text-muted-foreground">{service.name_en}</bdi></span></span> },
              { key: 'duration', label: f('duration_minutes'), render: (service) => t('reservations.catalog.minutes', { count: service.duration_minutes }) },
              { key: 'price', label: f('price'), render: (service) => <span className="tabular-nums">{formatMoney(service.price, locale)}</span> },
              { key: 'online', label: f('is_online'), render: (service) => (service.is_online ? '✓' : '—') },
              { key: 'actions', label: '', className: 'text-end', render: (service) => canEdit && <ServiceDialog service={service} companies={companies} resources={resources} staff={staff} branches={branches} /> },
            ]}
          />
        </TabsContent>

        <TabsContent value="resources" className="flex flex-col gap-3">
          {canEdit && <div className="flex justify-end"><ResourceDialog branches={branches} kinds={kinds} /></div>}
          <DataTable<ResourceRow>
            rows={resources}
            empty={t('reservations.catalog.none')}
            columns={[
              { key: 'name', label: f('name_ar'), render: (resource) => <span className={resource.is_active ? undefined : 'text-muted-foreground line-through'}><span className="block font-medium">{resource.name_ar}</span><bdi className="text-xs text-muted-foreground">{resource.name_en}</bdi></span> },
              { key: 'branch', label: f('branch'), render: (resource) => branchName(resource.branch_id) },
              { key: 'kind', label: f('kind'), render: (resource) => t(`reservations.resource_kind.${resource.kind}`) },
              { key: 'capacity', label: f('capacity'), render: (resource) => resource.capacity },
              { key: 'actions', label: '', className: 'text-end', render: (resource) => canEdit && <ResourceDialog resource={resource} branches={branches} kinds={kinds} /> },
            ]}
          />
        </TabsContent>

        <TabsContent value="hours" className="grid gap-4 lg:grid-cols-2">
          {settings.map((item) => <SettingsCard key={item.branch_id} settings={item} />)}
        </TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
