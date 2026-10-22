import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { FilePlus2, Pencil, Plus, Send, UserPlus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

function InviteDialog({ client }) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const form = useForm({ name: client.contact_name ?? '', email: client.contact_email ?? '', locale: 'ar' });

  function submit(event) {
    event.preventDefault();
    form.post(`/admin/clients/${client.id}/users`, {
      preserveScroll: true,
      onSuccess: () => { setOpen(false); form.reset(); },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline"><UserPlus data-icon="inline-start" />{t('admin.clients.users.invite')}</Button>
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>{t('admin.clients.users.invite_title')}</DialogTitle>
            <DialogDescription>{t('admin.clients.users.invite_text')}</DialogDescription>
          </DialogHeader>
          <FormField label={t('admin.clients.users.name')} htmlFor="invite-name" error={form.errors.name}>
            <Input id="invite-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
          </FormField>
          <FormField label={t('admin.clients.users.email')} htmlFor="invite-email" error={form.errors.email}>
            <Input id="invite-email" type="email" dir="ltr" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
          </FormField>
          <FormField label={t('admin.clients.users.locale')} htmlFor="invite-locale" error={form.errors.locale}>
            <SelectInput
              id="invite-locale"
              value={form.data.locale}
              onChange={(value) => form.setData('locale', value)}
              options={[{ value: 'ar', label: t('admin.common.arabic') }, { value: 'en', label: t('admin.common.english') }]}
            />
          </FormField>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}><Send data-icon="inline-start" />{t('admin.clients.users.send')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function ClientShow({ client, users, projects, invoices }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();
  const resend = useForm({});

  return (
    <AdminLayout title={client.name}>
      <PageHeader
        title={client.name}
        actions={can('clients.manage') && (
          <Button asChild variant="outline"><Link href={`/admin/clients/${client.id}/edit`}><Pencil data-icon="inline-start" />{t('admin.common.edit')}</Link></Button>
        )}
      >
        <div className="mt-2"><StatusBadge group="client_status" value={client.status} /></div>
      </PageHeader>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardContent>
            <DetailList items={[
              { label: t('admin.clients.fields.name_ar'), value: client.name_ar },
              { label: t('admin.clients.fields.name_en'), value: <bdi>{client.name_en}</bdi> },
              { label: t('admin.clients.fields.contact_name'), value: client.contact_name },
              { label: t('admin.clients.fields.contact_email'), value: client.contact_email && <span dir="ltr">{client.contact_email}</span> },
              { label: t('admin.clients.fields.contact_phone'), value: client.contact_phone && <span dir="ltr">{client.contact_phone}</span> },
              { label: t('admin.common.created_at'), value: formatDate(client.created_at, locale) },
              { label: t('admin.clients.fields.notes'), value: client.notes, wide: true },
            ]} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle>{t('admin.clients.users.title')}</CardTitle>
            {can('clients.manage') && <InviteDialog client={client} />}
          </CardHeader>
          <CardContent>
            {users.length === 0 && <p className="text-sm text-muted-foreground">{t('admin.clients.users.none')}</p>}
            <ul className="divide-y">
              {users.map((user) => (
                <li key={user.id} className="flex items-start justify-between gap-2 py-3">
                  <div className="min-w-0">
                    <div className="truncate text-sm font-medium">{user.name}</div>
                    <div className="truncate text-xs text-muted-foreground" dir="ltr">{user.email}</div>
                    <div className="mt-1 text-xs text-muted-foreground">
                      {user.pending
                        ? <Badge variant="outline" className="border-transparent bg-amber-100 text-amber-800">{t('admin.clients.users.pending')}</Badge>
                        : `${t('admin.clients.users.last_login')}: ${user.last_login_at ? formatDate(user.last_login_at, locale) : t('admin.clients.users.never')}`}
                    </div>
                  </div>
                  {can('clients.manage') && user.pending && (
                    <Button
                      size="xs"
                      variant="ghost"
                      disabled={resend.processing}
                      onClick={() => resend.post(`/admin/clients/${client.id}/users/${user.id}/invite`, { preserveScroll: true })}
                    >
                      {t('admin.clients.users.resend')}
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </div>

      <div className="mt-6">
        <PageHeader
          title={t('admin.clients.projects')}
          actions={can('projects.manage') && (
            <Button asChild size="sm" variant="outline"><Link href={`/admin/projects/create?client_id=${client.id}`}><Plus data-icon="inline-start" />{t('admin.projects.new')}</Link></Button>
          )}
        />
        <DataTable
          rows={projects}
          empty={t('admin.clients.no_projects')}
          rowHref={can('projects.view', 'projects.manage') ? (project) => `/admin/projects/${project.slug}` : undefined}
          columns={[
            { key: 'name', label: t('admin.projects.fields.name'), render: (project) => <span className="font-medium">{project.name}</span> },
            { key: 'type', label: t('admin.projects.fields.type'), render: (project) => <StatusBadge group="project_type" value={project.type} /> },
            { key: 'status', label: t('admin.projects.fields.status'), render: (project) => <StatusBadge group="project_status" value={project.status} /> },
          ]}
        />
      </div>

      {can('invoices.view', 'invoices.manage') && (
        <div className="mt-6">
          <PageHeader
            title={t('admin.clients.invoices')}
            actions={can('invoices.manage') && (
              <Button asChild size="sm" variant="outline"><Link href={`/admin/invoices/create?client_id=${client.id}`}><FilePlus2 data-icon="inline-start" />{t('admin.invoices.new')}</Link></Button>
            )}
          />
          <DataTable
            rows={invoices}
            rowHref={(invoice) => `/admin/invoices/${invoice.id}`}
            columns={[
              { key: 'number', label: t('admin.invoices.fields.number'), render: (invoice) => <span className="font-mono text-xs" dir="ltr">{invoice.number}</span> },
              { key: 'issue_date', label: t('admin.invoices.fields.issue_date'), render: (invoice) => formatDate(invoice.issue_date, locale) },
              { key: 'total', label: t('admin.invoices.fields.total'), render: (invoice) => formatMoney(invoice.total, locale) },
              { key: 'status', label: t('admin.invoices.fields.status'), render: (invoice) => <StatusBadge group="invoice_status" value={invoice.status} /> },
            ]}
          />
        </div>
      )}
    </AdminLayout>
  );
}
