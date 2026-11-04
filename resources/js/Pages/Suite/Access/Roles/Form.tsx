import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import PermissionMatrix from '@/components/suite/PermissionMatrix';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { AccessRoleResource, FeatureMatrix } from '@/types/suite';

interface Props {
  role: AccessRoleResource | null;
  matrix: FeatureMatrix;
  grantable: string[];
}

export default function RoleForm({ role, matrix, grantable }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const { isOwner } = useAccess();
  const form = useForm({
    name_ar: role?.name_ar ?? '',
    name_en: role?.name_en ?? '',
    permissions: role?.permissions ?? [] as string[],
  });
  const { data, setData, errors, processing } = form;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    role ? form.put(url(`/access/roles/${role.id}`)) : form.post(url('/access/roles'));
  }

  return (
    <SuiteLayout title={role ? t('suite.access.edit_role') : t('suite.access.new_role')}>
      <form onSubmit={submit} className="flex max-w-5xl flex-col gap-4">
        <Card>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <FormField label={t('suite.access.fields.name_ar')} htmlFor="name_ar" error={errors.name_ar}>
              <Input id="name_ar" dir="rtl" value={data.name_ar} onChange={(event) => setData('name_ar', event.target.value)} />
            </FormField>
            <FormField label={t('suite.access.fields.name_en')} htmlFor="name_en" error={errors.name_en}>
              <Input id="name_en" dir="ltr" value={data.name_en} onChange={(event) => setData('name_en', event.target.value)} />
            </FormField>
          </CardContent>
        </Card>
        <FormField error={errors.permissions}>
          <PermissionMatrix matrix={matrix} value={data.permissions} onChange={(keys) => setData('permissions', keys)} grantable={grantable} isOwner={isOwner} />
        </FormField>
        <FormActions processing={processing} cancelHref={url('/access/roles')} />
      </form>
    </SuiteLayout>
  );
}
