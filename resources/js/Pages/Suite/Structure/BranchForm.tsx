import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { BranchResource, CompanyResource } from '@/types/suite';

interface Props {
  company: CompanyResource;
  branch: BranchResource | null;
}

export default function BranchForm({ company, branch }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const f = (field: string) => t(`suite.structure.fields.${field}`);
  const form = useForm({
    code: branch?.code ?? '',
    name_ar: branch?.name_ar ?? '',
    name_en: branch?.name_en ?? '',
    city_ar: branch?.city_ar ?? '',
    city_en: branch?.city_en ?? '',
    status: branch?.status ?? 'active',
  });
  const { data, setData, errors, processing } = form;
  type Field = keyof typeof data;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    branch ? form.put(url(`/structure/branches/${branch.id}`)) : form.post(url(`/structure/companies/${company.id}/branches`));
  }

  const text = (field: Field, props: React.ComponentProps<typeof Input> = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <Input id={field} value={data[field]} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );

  return (
    <SuiteLayout title={branch ? t('suite.structure.edit_branch') : t('suite.structure.new_branch')}>
      <PageHeader title={branch ? branch.name : t('suite.structure.new_branch')} description={company.name} />
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {text('code', { dir: 'ltr', disabled: branch?.is_head_office })}
            <FormField label={f('status')} htmlFor="status" error={errors.status}>
              <SelectInput
                id="status"
                value={data.status}
                onChange={(value) => setData('status', value)}
                options={[{ value: 'active', label: t('suite.structure.status_active') }, { value: 'inactive', label: t('suite.structure.status_inactive') }]}
              />
            </FormField>
            {text('name_ar', { dir: 'rtl' })}
            {text('name_en', { dir: 'ltr' })}
            {text('city_ar', { dir: 'rtl' })}
            {text('city_en', { dir: 'ltr' })}
            <div className="sm:col-span-2">
              <FormActions processing={processing} cancelHref={url('/structure')} />
            </div>
          </form>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
