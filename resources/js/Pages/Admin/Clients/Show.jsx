import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Briefcase, FilePlus2, Pencil, Plus, Send, UserPlus } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
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
import { Textarea } from '@/components/ui/textarea';
import PlanCard from '@/components/admin/PlanCard';
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

function SubscriptionDialog({ client, subscription, modules, statuses }) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const f = (field) => t(`admin.clients.suite.fields.${field}`);
  const form = useForm({
    module: subscription?.module ?? 'hr',
    status: subscription?.status ?? 'active',
    quantity: subscription?.quantity ?? 10,
    unit_price: subscription?.unit_price ?? '150.00',
    starts_on: subscription?.starts_on ?? new Date().toISOString().slice(0, 10),
    ends_on: subscription?.ends_on ?? '',
    notes: subscription?.notes ?? '',
  });

  function submit(event) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    subscription
      ? form.put(`/admin/clients/${client.id}/subscriptions/${subscription.id}`, options)
      : form.post(`/admin/clients/${client.id}/subscriptions`, options);
  }

  const field = (name, input) => (
    <FormField label={f(name)} htmlFor={`sub-${name}`} error={form.errors[name]}>{input}</FormField>
  );

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {subscription
          ? <Button size="icon-sm" variant="ghost" aria-label={t('admin.common.edit')}><Pencil /></Button>
          : <Button size="sm" variant="outline"><Plus data-icon="inline-start" />{t('admin.clients.suite.add_subscription')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2">
            <DialogTitle>{subscription ? t('admin.clients.suite.edit_subscription') : t('admin.clients.suite.add_subscription')}</DialogTitle>
          </DialogHeader>
          {field('module', <SelectInput id="sub-module" value={form.data.module} onChange={(value) => form.setData('module', value)} options={modules} />)}
          {field('status', <SelectInput id="sub-status" value={form.data.status} onChange={(value) => form.setData('status', value)} options={statuses} />)}
          {field('quantity', <Input id="sub-quantity" type="number" min="1" dir="ltr" value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} />)}
          {field('unit_price', <Input id="sub-unit_price" type="number" step="0.01" min="0" dir="ltr" value={form.data.unit_price} onChange={(event) => form.setData('unit_price', event.target.value)} />)}
          {field('starts_on', <Input id="sub-starts_on" type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} />)}
          {field('ends_on', <Input id="sub-ends_on" type="date" dir="ltr" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} />)}
          <FormField label={f('notes')} htmlFor="sub-notes" error={form.errors.notes} className="sm:col-span-2">
            <Textarea id="sub-notes" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
          </FormField>
          <DialogFooter className="sm:col-span-2">
            <Button type="submit" disabled={form.processing}>{t('admin.common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function SuiteCard({ client, subscriptions, suite, canManage }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2"><Briefcase className="size-4 text-brass" />{t('admin.clients.suite.title')}</CardTitle>
        <div className="flex gap-2">
          {suite.provisioned
            ? <Badge variant="outline" className="border-transparent bg-emerald-100 text-emerald-800">{t('admin.clients.suite.active')}</Badge>
            : canManage && (
              <ConfirmAction href={`/admin/clients/${client.id}/suite`} title={t('admin.clients.suite.provision')} description={t('admin.clients.suite.provision_text')} confirmLabel={t('admin.clients.suite.provision')} size="sm" variant="outline">
                {t('admin.clients.suite.provision')}
              </ConfirmAction>
            )}
          {canManage && <SubscriptionDialog client={client} modules={suite.modules} statuses={suite.statuses} />}
        </div>
      </CardHeader>
      <CardContent>
        {!suite.provisioned && <p className="mb-3 text-sm text-muted-foreground">{t('admin.clients.suite.not_provisioned')}</p>}
        {subscriptions.length === 0
          ? <p className="text-sm text-muted-foreground">{t('admin.clients.suite.none')}</p>
          : (
            <ul className="divide-y">
              {subscriptions.map((subscription) => (
                <li key={subscription.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 font-medium">
                      {t(`suite.module.${subscription.module}`)}
                      <StatusBadge group="subscription_status" value={subscription.status} file="suite" />
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                      {subscription.quantity} {t(`suite.billing_unit.${subscription.billing_unit}`)} × {formatMoney(subscription.unit_price, locale)} · {formatDate(subscription.starts_on, locale)}{subscription.ends_on && ` → ${formatDate(subscription.ends_on, locale)}`}
                    </div>
                  </div>
                  <span className="flex items-center gap-1">
                    {canManage && can('invoices.manage') && (
                      <ConfirmAction href={`/admin/clients/${client.id}/subscriptions/${subscription.id}/invoice`} method="post" size="xs" variant="outline" title={t('admin.clients.suite.invoice')} description={`${t(`suite.module.${subscription.module}`)} · ${subscription.quantity} × ${formatMoney(subscription.unit_price, locale)}`}>
                        {t('admin.clients.suite.invoice')}
                      </ConfirmAction>
                    )}
                    {canManage && <SubscriptionDialog client={client} subscription={subscription} modules={suite.modules} statuses={suite.statuses} />}
                  </span>
                </li>
              ))}
            </ul>
          )}
      </CardContent>
    </Card>
  );
}

export default function ClientShow({ client, users, projects, invoices, subscriptions, suite, plan }) {
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
        <PlanCard client={client} plan={plan} canManage={can('clients.manage')} />
        <SuiteCard client={client} subscriptions={subscriptions} suite={suite} canManage={can('clients.manage')} />
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
