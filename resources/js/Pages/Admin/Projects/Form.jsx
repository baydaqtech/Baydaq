import { useForm } from '@inertiajs/react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

export default function ProjectForm({ project, clientId, options }) {
  const t = useT();
  const f = (field) => t(`admin.projects.fields.${field}`);
  const form = useForm({
    client_id: project?.client_id ?? clientId ?? '',
    name_ar: project?.name_ar ?? '',
    name_en: project?.name_en ?? '',
    slug: project?.slug ?? '',
    description_ar: project?.description_ar ?? '',
    description_en: project?.description_en ?? '',
    type: project?.type ?? 'client_owned',
    relationship: project?.relationship ?? '',
    partnership_share: project?.partnership_share ?? '',
    status: project?.status ?? 'planning',
    progress: project?.progress ?? 0,
    started_at: project?.started_at ?? '',
    expected_launch_at: project?.expected_launch_at ?? '',
    launched_at: project?.launched_at ?? '',
    drive_url: project?.drive_url ?? '',
    repo_url: project?.repo_url ?? '',
    app_url: project?.app_url ?? '',
    logo: null,
  });
  const { data, setData, errors, processing } = form;
  const poweredByBaydaq = data.type === 'powered_by_baydaq';

  function submit(event) {
    event.preventDefault();
    // Multipart for the logo; updates spoof PUT because files can't travel in a real PUT.
    if (project) {
      form.transform((values) => ({ ...values, _method: 'put' }));
      form.post(`/admin/projects/${project.slug}`, { forceFormData: true });
    } else {
      form.post('/admin/projects', { forceFormData: true });
    }
  }

  const input = (field, { hint, ...props } = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]} hint={hint}>
      <Input id={field} value={data[field] ?? ''} onChange={(event) => setData(field, event.target.value)} aria-invalid={!!errors[field]} {...props} />
    </FormField>
  );
  const select = (field, choices, props = {}) => (
    <FormField label={f(field)} htmlFor={field} error={errors[field]}>
      <SelectInput id={field} value={data[field]} onChange={(value) => setData(field, value)} options={choices} invalid={!!errors[field]} {...props} />
    </FormField>
  );

  return (
    <AdminLayout title={project ? t('admin.projects.edit') : t('admin.projects.new')}>
      <Card className="max-w-4xl">
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            {select('client_id', options.clients)}
            {input('slug', { dir: 'ltr', hint: f('slug_hint') })}
            {input('name_ar', { dir: 'rtl' })}
            {input('name_en', { dir: 'ltr' })}
            {select('type', options.types)}
            {poweredByBaydaq ? select('relationship', options.relationships) : <div className="hidden sm:block" />}
            {poweredByBaydaq && data.relationship === 'partnership' && input('partnership_share', { type: 'number', min: 0, max: 100, step: '0.01', dir: 'ltr' })}
            {select('status', options.statuses)}
            {input('progress', { type: 'number', min: 0, max: 100, dir: 'ltr' })}
            {input('started_at', { type: 'date' })}
            {input('expected_launch_at', { type: 'date' })}
            {input('launched_at', { type: 'date' })}
            {input('drive_url', { type: 'url', dir: 'ltr' })}
            {input('repo_url', { type: 'url', dir: 'ltr' })}
            {input('app_url', { type: 'url', dir: 'ltr' })}
            <FormField label={f('logo')} htmlFor="logo" error={errors.logo} hint={f('logo_hint')}>
              <Input id="logo" type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => setData('logo', event.target.files[0] ?? null)} />
            </FormField>
            {project?.logo_url && <img src={project.logo_url} alt="" className="size-16 rounded-md border object-contain" />}
            <FormField label={f('description_ar')} htmlFor="description_ar" error={errors.description_ar} className="sm:col-span-2">
              <Textarea id="description_ar" dir="rtl" rows={3} value={data.description_ar} onChange={(event) => setData('description_ar', event.target.value)} />
            </FormField>
            <FormField label={f('description_en')} htmlFor="description_en" error={errors.description_en} className="sm:col-span-2">
              <Textarea id="description_en" dir="ltr" rows={3} value={data.description_en} onChange={(event) => setData('description_en', event.target.value)} />
            </FormField>
            <div className="sm:col-span-2">
              <FormActions processing={processing} cancelHref={project ? `/admin/projects/${project.slug}` : '/admin/projects'} />
            </div>
          </form>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
