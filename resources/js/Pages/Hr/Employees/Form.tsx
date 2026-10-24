import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { BranchOption, CatalogItem, EmployeeResource } from '@/types/hr';
import type { Option } from '@/types/resources';

interface Props {
  employee: EmployeeResource | null;
  companies: Option[];
  branches: BranchOption[];
  sections: CatalogItem[];
  jobs: CatalogItem[];
  managers: Option[];
  statuses: Option[];
  genders: Option[];
  id_types: Option[];
}

export default function EmployeeForm({ employee, companies, branches, sections, jobs, managers, statuses, genders, id_types: idTypes }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const f = (field: string) => t(`hr.employees.fields.${field}`);
  const form = useForm({
    company_id: employee?.company_id ?? companies[0]?.value ?? '',
    branch_id: employee?.branch_id ?? '',
    employee_number: employee?.employee_number ?? '',
    name_ar: employee?.name_ar ?? '',
    name_en: employee?.name_en ?? '',
    email: employee?.email ?? '',
    phone: employee?.phone ?? '',
    section_id: employee?.section_id ?? '',
    job_id: employee?.job_id ?? '',
    manager_id: employee?.manager_id ?? '',
    id_type: employee?.id_type ?? '',
    national_id: employee?.national_id ?? '',
    nationality: employee?.nationality ?? 'SA',
    gender: employee?.gender ?? '',
    birth_date: employee?.birth_date ?? '',
    hired_on: employee?.hired_on ?? '',
    status: employee?.status ?? 'draft',
    iban: employee?.iban ?? '',
    notes: employee?.notes ?? '',
  });
  const { data, setData, errors, processing } = form;
  type Field = keyof typeof data;

  const companyBranches = branches.filter((branch) => branch.company_id === Number(data.company_id));
  const blockedHere = (item: CatalogItem) => (item.blocked_company_ids ?? []).includes(Number(data.company_id));
  const sectionOptions = sections.filter((section) => !blockedHere(section) || section.id === employee?.section_id).map((section) => ({ value: section.id, label: section.name }));
  const jobOptions = jobs
    .filter((job) => (!data.section_id || (job.section_ids ?? []).includes(Number(data.section_id))) && (!blockedHere(job) || job.id === employee?.job_id))
    .map((job) => ({ value: job.id, label: job.name }));

  function submit(event: React.FormEvent) {
    event.preventDefault();
    employee ? form.put(url(`/hr/employees/${employee.id}`)) : form.post(url('/hr/employees'));
  }

  const text = (field: Field, props: React.ComponentProps<typeof Input> = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <Input id={field} value={String(data[field] ?? '')} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );
  const select = (field: Field, options: Option[], extra: { emptyLabel?: string; disabled?: boolean } = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <SelectInput id={field} value={data[field] as string | number} onChange={(value) => setData(field, value === '' ? '' : (Number.isNaN(Number(value)) ? value : Number(value)))} options={options} emptyLabel={extra.emptyLabel} />
    </FormField>
  );

  const canSeeIban = !employee || employee.can.view_iban;
  const canSeeId = !employee || employee.can.view_national_id;

  return (
    <SuiteLayout title={employee ? t('hr.employees.edit') : t('hr.employees.new')}>
      <form onSubmit={submit} className="flex max-w-4xl flex-col gap-4">
        <Card>
          <CardHeader><CardTitle>{t('hr.employees.tabs.profile')}</CardTitle></CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            {!employee && (
              <>
                {select('company_id', companies)}
                {select('branch_id', companyBranches)}
              </>
            )}
            {text('employee_number', { dir: 'ltr' })}
            {!employee && select('status', statuses)}
            {text('name_ar', { dir: 'rtl' })}
            {text('name_en', { dir: 'ltr' })}
            {select('section_id', sectionOptions, { emptyLabel: '—' })}
            {select('job_id', jobOptions, { emptyLabel: '—' })}
            {select('manager_id', managers.filter((manager) => manager.value !== employee?.id), { emptyLabel: '—' })}
            {text('hired_on', { type: 'date', dir: 'ltr' })}
            {text('email', { type: 'email', dir: 'ltr' })}
            {text('phone', { dir: 'ltr' })}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>{f('national_id')}</CardTitle></CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            {select('id_type', idTypes, { emptyLabel: '—' })}
            {canSeeId && text('national_id', { dir: 'ltr', inputMode: 'numeric' })}
            {text('nationality', { dir: 'ltr', maxLength: 2 })}
            {select('gender', genders, { emptyLabel: '—' })}
            {text('birth_date', { type: 'date', dir: 'ltr' })}
            {canSeeIban && text('iban', { dir: 'ltr' })}
            <FormField label={f('notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
              <Textarea id="notes" rows={3} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
            </FormField>
          </CardContent>
        </Card>
        <FormActions processing={processing} cancelHref={employee ? url(`/hr/employees/${employee.id}`) : url('/hr/employees')} />
      </form>
    </SuiteLayout>
  );
}
