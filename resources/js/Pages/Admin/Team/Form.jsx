import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import SwitchField from '@/components/platform/SwitchField';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout';
import { useCopy, useT } from '@/lib/i18n';

export default function TeamForm({ member, permissions }) {
  const t = useT();
  const f = (field) => t(`admin.team.fields.${field}`);
  // Permission names contain dots (clients.view), so labels come straight from the map.
  const permissionLabels = useCopy('admin.team.permissions');
  const form = useForm({
    name: member?.name ?? '',
    email: member?.email ?? '',
    locale: member?.locale ?? 'ar',
    permissions: member?.permissions ?? [],
  });
  const { data, setData, errors, processing } = form;

  function submit(event) {
    event.preventDefault();
    member ? form.put(`/admin/team/${member.id}`) : form.post('/admin/team');
  }

  function toggle(permission, on) {
    setData('permissions', on ? [...data.permissions, permission] : data.permissions.filter((name) => name !== permission));
  }

  return (
    <AdminLayout title={member ? t('admin.team.edit') : t('admin.team.new')}>
      <Card className="max-w-2xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <FormField label={f('name')} htmlFor="name" error={errors.name}>
              <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} />
            </FormField>
            <FormField label={f('email')} htmlFor="email" error={errors.email}>
              <Input id="email" type="email" dir="ltr" value={data.email} disabled={!!member} onChange={(event) => setData('email', event.target.value)} />
            </FormField>
            <FormField label={f('locale')} htmlFor="locale" error={errors.locale}>
              <SelectInput
                id="locale"
                value={data.locale}
                onChange={(value) => setData('locale', value)}
                options={[{ value: 'ar', label: t('admin.common.arabic') }, { value: 'en', label: t('admin.common.english') }]}
              />
            </FormField>
            <fieldset className="grid gap-2 sm:col-span-2">
              <legend className="mb-2 text-sm font-medium">{f('permissions')}</legend>
              <div className="grid gap-2 sm:grid-cols-2">
                {permissions.map((permission) => (
                  <SwitchField
                    key={permission}
                    id={`perm-${permission}`}
                    label={permissionLabels[permission] ?? permission}
                    checked={data.permissions.includes(permission)}
                    onChange={(on) => toggle(permission, on)}
                  />
                ))}
              </div>
              {errors.permissions && <p className="text-sm text-destructive">{errors.permissions}</p>}
            </fieldset>
            <div className="sm:col-span-2"><FormActions processing={processing} cancelHref="/admin/team" /></div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
