import { Link, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export default function RequestShow({ request, statuses }) {
  const t = useT();
  const { locale } = useLocale();
  const f = (field) => t(`admin.requests.fields.${field}`);
  const open = request.status === 'new' || request.status === 'reviewing';
  const form = useForm({ status: open ? request.status : 'reviewing', admin_notes: request.admin_notes ?? '' });

  function submit(event) {
    event.preventDefault();
    form.put(`/admin/requests/${request.id}`, { preserveScroll: true });
  }

  return (
    <AdminLayout title={request.name}>
      <PageHeader
        title={request.name}
        description={request.company}
        actions={open && (
          <>
            <ConfirmAction href={`/admin/requests/${request.id}/approve`} title={t('admin.requests.approve')} description={t('admin.requests.approve_text')}>
              <Check data-icon="inline-start" />{t('admin.requests.approve')}
            </ConfirmAction>
            <ConfirmAction href={`/admin/requests/${request.id}/reject`} variant="destructive" title={t('admin.requests.reject')} description={t('admin.requests.reject_text')}>
              <X data-icon="inline-start" />{t('admin.requests.reject')}
            </ConfirmAction>
          </>
        )}
      >
        <div className="mt-2 flex gap-2">
          <StatusBadge group="request_type" value={request.type} />
          <StatusBadge group="request_status" value={request.status} />
        </div>
      </PageHeader>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardContent>
            <DetailList items={[
              { label: f('email'), value: <a href={`mailto:${request.email}`} dir="ltr" className="underline-offset-4 hover:underline">{request.email}</a> },
              { label: f('phone'), value: request.phone && <span dir="ltr">{request.phone}</span> },
              { label: f('received'), value: formatDate(request.created_at, locale) },
              { label: f('locale'), value: t(`common.language.${request.locale}`) },
              request.services.length > 0 && { label: f('services'), value: request.services.join('، '), wide: true },
              { label: f('message'), value: <span className="whitespace-pre-line">{request.message}</span>, wide: true },
              request.client && { label: t('admin.requests.client'), value: <Link href={`/admin/clients/${request.client.id}`} className="underline-offset-4 hover:underline">{request.client.name}</Link> },
              request.handler && { label: f('handled_by'), value: request.handler },
            ]} />
          </CardContent>
        </Card>
        <Card>
          <CardContent>
            <form onSubmit={submit} className="flex flex-col gap-4">
              {open && (
                <FormField label={f('status')} htmlFor="status" error={form.errors.status}>
                  <SelectInput id="status" value={form.data.status} onChange={(value) => form.setData('status', value)} options={statuses.filter((option) => ['new', 'reviewing'].includes(option.value))} />
                </FormField>
              )}
              <FormField label={f('admin_notes')} htmlFor="admin_notes" error={form.errors.admin_notes}>
                <Textarea id="admin_notes" rows={5} value={form.data.admin_notes} onChange={(event) => form.setData('admin_notes', event.target.value)} />
              </FormField>
              <Button type="submit" variant="outline" disabled={form.processing}>{t('admin.requests.save_notes')}</Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
