import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, FilePlus2, KeyRound, Pause, Pencil, Play, Plus } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import Repeater from '@/components/platform/Repeater';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

function ItemsForm({ project, kind, initial, blank, renderRow, addLabel, readOnly }) {
  const t = useT();
  const form = useForm({ items: initial });

  function submit(event) {
    event.preventDefault();
    form.put(`/admin/projects/${project.slug}/${kind}`, { preserveScroll: true });
  }

  const errorFor = (index, field) => form.errors[`items.${index}.${field}`];

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Repeater
        items={form.data.items}
        onChange={(items) => form.setData('items', items)}
        blank={blank}
        addLabel={addLabel}
        renderRow={(item, update, index) => renderRow(item, update, (field) => errorFor(index, field))}
      />
      {!readOnly && (
        <div>
          <Button type="submit" disabled={form.processing || !form.isDirty}>{form.processing ? t('admin.common.saving') : t('admin.common.save')}</Button>
        </div>
      )}
    </form>
  );
}

function ServiceDialog({ project, options, availableServices, existing }) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const s = (field) => t(`admin.projects.services.${field}`);
  const form = useForm(existing ? {
    plan: existing.plan ?? '',
    price: existing.price ?? '',
    pricing_model: existing.pricing_model,
    started_at: existing.started_at ?? '',
    notes: existing.notes ?? '',
    status: existing.status,
  } : { service_id: '', plan: '', price: '', pricing_model: 'monthly', started_at: new Date().toISOString().slice(0, 10), notes: '' });

  function pickService(value) {
    const service = availableServices.find((option) => String(option.value) === value);
    form.setData({ ...form.data, service_id: value, pricing_model: service?.pricing_model ?? 'monthly', price: service?.default_price ?? '' });
  }

  function submit(event) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    existing
      ? form.put(`/admin/projects/${project.slug}/services/${existing.id}`, options)
      : form.post(`/admin/projects/${project.slug}/services`, options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {existing
          ? <Button size="xs" variant="ghost"><Pencil data-icon="inline-start" />{t('admin.common.edit')}</Button>
          : <Button size="sm" variant="outline" disabled={availableServices.length === 0}><Plus data-icon="inline-start" />{s('attach')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{existing ? existing.service.name : s('attach')}</DialogTitle></DialogHeader>
          {!existing && (
            <FormField label={s('service')} htmlFor="service_id" error={form.errors.service_id}>
              <SelectInput id="service_id" value={form.data.service_id} onChange={pickService} options={availableServices} />
            </FormField>
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label={s('plan')} htmlFor="plan" error={form.errors.plan}>
              <Input id="plan" value={form.data.plan} onChange={(event) => form.setData('plan', event.target.value)} />
            </FormField>
            <FormField label={s('price')} htmlFor="price" error={form.errors.price}>
              <Input id="price" type="number" min="0" step="0.01" dir="ltr" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} />
            </FormField>
            <FormField label={s('pricing_model')} htmlFor="pricing_model" error={form.errors.pricing_model}>
              <SelectInput id="pricing_model" value={form.data.pricing_model} onChange={(value) => form.setData('pricing_model', value)} options={options.pricingModels} />
            </FormField>
            <FormField label={s('started_at')} htmlFor="started_at" error={form.errors.started_at}>
              <Input id="started_at" type="date" value={form.data.started_at} onChange={(event) => form.setData('started_at', event.target.value)} />
            </FormField>
            {existing && (
              <FormField label={s('status')} htmlFor="ps-status" error={form.errors.status}>
                <SelectInput id="ps-status" value={form.data.status} onChange={(value) => form.setData('status', value)} options={options.serviceStatuses} />
              </FormField>
            )}
          </div>
          <FormField label={s('notes')} htmlFor="ps-notes" error={form.errors.notes}>
            <Textarea id="ps-notes" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
          </FormField>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}>{t('admin.common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function UsageDialog({ project, projectService }) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const form = useForm({ quantity: '', date: new Date().toISOString().slice(0, 10) });
  const s = (field) => t(`admin.projects.services.${field}`);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="ghost">{s('record_usage')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(`/admin/projects/${project.slug}/services/${projectService.id}/usage`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{s('record_usage')} · {projectService.service.name}</DialogTitle></DialogHeader>
          <FormField label={s('quantity')} htmlFor="usage-quantity" error={form.errors.quantity}>
            <Input id="usage-quantity" type="number" step="0.01" dir="ltr" value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} />
          </FormField>
          <FormField label={s('date')} htmlFor="usage-date" error={form.errors.date}>
            <Input id="usage-date" type="date" dir="ltr" value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} />
          </FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('admin.common.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function ProjectShow({ project, tab, availableServices, options }) {
  const t = useT();
  const can = useCan();
  const { locale, dir } = useLocale();
  const manage = can('projects.manage');
  const f = (field) => t(`admin.projects.fields.${field}`);
  const m = (field) => t(`admin.projects.milestones.${field}`);
  const r = (field) => t(`admin.projects.resources.${field}`);
  const fl = (field) => t(`admin.projects.files.${field}`);
  const s = (field) => t(`admin.projects.services.${field}`);
  const link = (url) => url && <a href={url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-walnut underline-offset-4 hover:underline" dir="ltr">{url}<ExternalLink className="size-3" /></a>;

  function toggleService(projectService) {
    router.put(`/admin/projects/${project.slug}/services/${projectService.id}`, {
      plan: projectService.plan,
      price: projectService.price,
      pricing_model: projectService.pricing_model,
      started_at: projectService.started_at,
      notes: projectService.notes,
      status: projectService.status === 'active' ? 'paused' : 'active',
    }, { preserveScroll: true });
  }

  return (
    <AdminLayout title={project.name}>
      <PageHeader
        title={project.name}
        description={project.client?.name}
        actions={(
          <>
            {can('invoices.manage') && (
              <Button asChild variant="outline"><Link href={`/admin/invoices/create?client_id=${project.client_id}&project_id=${project.id}`}><FilePlus2 data-icon="inline-start" />{t('admin.invoices.new')}</Link></Button>
            )}
            {manage && <Button asChild variant="outline"><Link href={`/admin/projects/${project.slug}/edit`}><Pencil data-icon="inline-start" />{t('admin.common.edit')}</Link></Button>}
          </>
        )}
      >
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <StatusBadge group="project_type" value={project.type} />
          {project.relationship && <StatusBadge group="project_relationship" value={project.relationship} />}
          <StatusBadge group="project_status" value={project.status} />
        </div>
      </PageHeader>

      <Tabs
        dir={dir}
        defaultValue={tab}
        onValueChange={(value) => window.history.replaceState(window.history.state, '', `?tab=${value}`)}
      >
        <TabsList className="mb-4 flex-wrap">
          {['overview', 'milestones', 'resources', 'files', 'services'].map((key) => (
            <TabsTrigger key={key} value={key}>{t(`admin.projects.tabs.${key}`)}</TabsTrigger>
          ))}
        </TabsList>

        <TabsContent value="overview">
          <Card>
            <CardContent className="flex flex-col gap-6">
              <div className="flex items-center gap-3">
                <Progress value={project.progress} className="h-2 max-w-sm" />
                <span className="text-sm text-muted-foreground">{project.progress}%</span>
              </div>
              <DetailList items={[
                { label: f('client'), value: project.client && <Link href={`/admin/clients/${project.client.id}`} className="underline-offset-4 hover:underline">{project.client.name}</Link> },
                project.relationship === 'partnership' && { label: f('partnership_share'), value: `${project.partnership_share}%` },
                { label: f('started_at'), value: formatDate(project.started_at, locale) },
                { label: f('expected_launch_at'), value: formatDate(project.expected_launch_at, locale) },
                { label: f('launched_at'), value: formatDate(project.launched_at, locale) },
                { label: f('drive_url'), value: link(project.drive_url) },
                { label: f('repo_url'), value: link(project.repo_url) },
                { label: f('app_url'), value: link(project.app_url) },
                { label: f('description_ar'), value: project.description_ar, wide: true },
                { label: f('description_en'), value: project.description_en && <span dir="ltr">{project.description_en}</span>, wide: true },
              ]} />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="milestones">
          <ItemsForm
            project={project}
            kind="milestones"
            readOnly={!manage}
            initial={project.milestones}
            blank={{ title: '', description: '', due_date: '', done: false }}
            addLabel={m('add')}
            renderRow={(item, update, error) => (
              <div className="grid gap-3 sm:grid-cols-[1fr_10rem_auto]">
                <FormField label={m('title')} error={error('title')}>
                  <Input value={item.title} onChange={(event) => update('title', event.target.value)} />
                </FormField>
                <FormField label={m('due_date')} error={error('due_date')}>
                  <Input type="date" value={item.due_date ?? ''} onChange={(event) => update('due_date', event.target.value)} />
                </FormField>
                <label className="mt-7 flex items-center gap-2 text-sm">
                  <Checkbox checked={!!item.done} onCheckedChange={(checked) => update('done', checked === true)} />
                  {m('done')}
                </label>
                <FormField label={m('description')} error={error('description')} className="sm:col-span-3">
                  <Textarea rows={2} value={item.description ?? ''} onChange={(event) => update('description', event.target.value)} />
                </FormField>
              </div>
            )}
          />
        </TabsContent>

        <TabsContent value="resources">
          <ItemsForm
            project={project}
            kind="resources"
            readOnly={!manage}
            initial={project.resources}
            blank={{ name: '', role: '', notes: '' }}
            addLabel={r('add')}
            renderRow={(item, update, error) => (
              <div className="grid gap-3 sm:grid-cols-3">
                <FormField label={r('name')} error={error('name')}>
                  <Input value={item.name} onChange={(event) => update('name', event.target.value)} />
                </FormField>
                <FormField label={r('role')} error={error('role')}>
                  <Input value={item.role ?? ''} onChange={(event) => update('role', event.target.value)} />
                </FormField>
                <FormField label={r('notes')} error={error('notes')}>
                  <Input value={item.notes ?? ''} onChange={(event) => update('notes', event.target.value)} />
                </FormField>
              </div>
            )}
          />
        </TabsContent>

        <TabsContent value="files">
          <ItemsForm
            project={project}
            kind="files"
            readOnly={!manage}
            initial={project.files}
            blank={{ title: '', url: '', category: 'other' }}
            addLabel={fl('add')}
            renderRow={(item, update, error) => (
              <div className="grid gap-3 sm:grid-cols-[1fr_1.4fr_10rem]">
                <FormField label={fl('title')} error={error('title')}>
                  <Input value={item.title} onChange={(event) => update('title', event.target.value)} />
                </FormField>
                <FormField label={fl('url')} error={error('url')}>
                  <Input type="url" dir="ltr" value={item.url} onChange={(event) => update('url', event.target.value)} />
                </FormField>
                <FormField label={fl('category')} error={error('category')}>
                  <SelectInput value={item.category} onChange={(value) => update('category', value)} options={options.fileCategories} />
                </FormField>
              </div>
            )}
          />
        </TabsContent>

        <TabsContent value="services">
          <PageHeader
            actions={manage && <ServiceDialog project={project} options={options} availableServices={availableServices} />}
            description={manage && availableServices.length === 0 ? s('all_attached') : undefined}
          />
          {project.services.length === 0 && <p className="text-sm text-muted-foreground">{s('none')}</p>}
          <div className="grid gap-3 md:grid-cols-2">
            {project.services.map((projectService) => (
              <Card key={projectService.id} className="gap-3 py-4">
                <CardContent className="flex flex-col gap-3 px-4">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="font-medium">{projectService.service.name}</div>
                      <div className="text-xs text-muted-foreground">{projectService.plan ?? '—'}</div>
                    </div>
                    <StatusBadge group="project_service_status" value={projectService.status} />
                  </div>
                  <DetailList className="sm:grid-cols-3" items={[
                    { label: s('price'), value: formatMoney(projectService.price, locale) },
                    { label: s('pricing_model'), value: t(`enums.pricing_model.${projectService.pricing_model}`) },
                    { label: s('started_at'), value: formatDate(projectService.started_at, locale) },
                  ]} />
                  {projectService.notes && <p className="text-sm text-muted-foreground">{projectService.notes}</p>}
                  <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <KeyRound className="size-3.5" />{projectService.connected ? s('connected') : s('not_connected')}
                    {projectService.last_used_at && <span>· {formatDate(projectService.last_used_at, locale)}</span>}
                  </div>
                  {manage && (
                    <div className="flex flex-wrap gap-1">
                      <ServiceDialog project={project} options={options} availableServices={availableServices} existing={projectService} />
                      <ConfirmAction href={`/admin/projects/${project.slug}/services/${projectService.id}/key`} method="post" size="xs" variant="ghost" title={projectService.connected ? s('rotate_key') : s('issue_key')}>
                        {projectService.connected ? s('rotate_key') : s('issue_key')}
                      </ConfirmAction>
                      <UsageDialog project={project} projectService={projectService} />
                      <Button size="xs" variant="ghost" onClick={() => toggleService(projectService)}>
                        {projectService.status === 'active'
                          ? <><Pause data-icon="inline-start" />{s('pause')}</>
                          : <><Play data-icon="inline-start" />{s('resume')}</>}
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>
      </Tabs>
    </AdminLayout>
  );
}
