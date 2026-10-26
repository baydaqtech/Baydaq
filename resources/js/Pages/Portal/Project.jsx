import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, BookOpen, ExternalLink, FolderOpen, KeyRound, Rocket, Send, Wallet } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import BrandMark from '@/components/platform/BrandMark';
import DetailList from '@/components/platform/DetailList';
import InvoiceTable from '@/components/platform/InvoiceTable';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import PortalLayout from '@/layouts/PortalLayout';
import { formatDate, formatMoney, formatNumber, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { serviceIcon } from '@/lib/serviceIcons';

function UsageBars({ history }) {
  const max = Math.max(1, ...history.map((month) => Number(month.quantity)));

  return (
    <div className="flex h-10 items-end gap-1" aria-hidden>
      {history.map((month) => (
        <div key={month.month} className="flex-1 rounded-sm bg-brass/70" style={{ height: `${Math.max(6, (Number(month.quantity) / max) * 100)}%` }} title={`${month.month}: ${month.quantity}`} />
      ))}
    </div>
  );
}

function CapForm({ projectSlug, service }) {
  const t = useT();
  const { locale } = useLocale();
  const s = (field) => t(`portal.services.${field}`);
  const form = useForm({ monthly_cap: service.monthly_cap ?? '' });
  const spent = Number(service.usage?.cost ?? 0);
  const cap = Number(service.monthly_cap ?? 0);
  const percent = cap > 0 ? Math.min(100, Math.round((spent / cap) * 100)) : null;

  return (
    <form className="flex flex-col gap-2" onSubmit={(event) => { event.preventDefault(); form.put(`/app/projects/${projectSlug}/services/${service.id}/cap`, { preserveScroll: true }); }}>
      <FormField label={s('cap')} htmlFor={`cap-${service.id}`} error={form.errors.monthly_cap} hint={s('cap_hint')}>
        <div className="flex gap-2">
          <Input id={`cap-${service.id}`} type="number" min="0" step="0.01" dir="ltr" placeholder={s('cap_none')} value={form.data.monthly_cap} onChange={(event) => form.setData('monthly_cap', event.target.value)} className="max-w-40" />
          <Button type="submit" size="sm" variant="outline" disabled={form.processing || !form.isDirty}>{s('cap_save')}</Button>
        </div>
      </FormField>
      {percent !== null && (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <Progress value={percent} className={`h-1.5 max-w-40 ${percent >= 100 ? '[&>div]:bg-red-600' : percent >= 80 ? '[&>div]:bg-amber-500' : ''}`} />
          {s('cap_of').replace(':percent', formatNumber(percent, locale))} · {formatMoney(spent, locale)}
        </div>
      )}
    </form>
  );
}

function DeliveryHealth({ health }) {
  const t = useT();
  const { locale } = useLocale();
  const s = (field) => t(`portal.services.${field}`);
  const rate = (value) => (value === null ? '—' : formatPercent(value, locale));

  return (
    <div className="rounded-lg border p-3">
      <div className="mb-2 text-xs text-muted-foreground">{s('health')}</div>
      <dl className="grid grid-cols-3 gap-2 text-center">
        {[['delivered', health.delivered_rate], ['read', health.read_rate], ['bounced', health.bounce_rate]].map(([key, value]) => (
          <div key={key}>
            <dt className="text-xs text-muted-foreground">{s(key)}</dt>
            <dd className={`text-sm font-semibold ${key === 'bounced' && value !== null && value >= 5 ? 'text-red-700' : ''}`}>{rate(value)}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

function ServiceCard({ service, projectSlug }) {
  const t = useT();
  const { locale } = useLocale();
  const s = (field) => t(`portal.services.${field}`);
  const Icon = serviceIcon(service.icon);
  const unit = service.unit ? t(`common.units.${service.unit}`) : null;
  const usage = service.usage;
  const hasUsage = usage && Number(usage.quantity) > 0;

  return (
    <Card className="gap-3">
      <CardContent className="flex flex-col gap-4">
        <div className="flex items-start gap-3">
          <span className="grid size-10 shrink-0 place-items-center rounded-full bg-brass/15 text-walnut"><Icon className="size-5" /></span>
          <div className="min-w-0 flex-1">
            <div className="flex items-center justify-between gap-2">
              <h3 className="font-semibold">{service.name}</h3>
              <StatusBadge group="project_service_status" value={service.status} />
            </div>
            {service.description && <p className="mt-1 text-sm text-muted-foreground">{service.description}</p>}
          </div>
        </div>
        <DetailList className="sm:grid-cols-3" items={[
          { label: s('plan'), value: service.plan },
          { label: s('price'), value: service.price && `${formatMoney(service.price, locale)}${unit && service.pricing_model === 'per_use' ? ` ${s('per_unit').replace(':unit', unit)}` : ''}` },
          { label: s('started_at'), value: formatDate(service.started_at, locale) },
        ]} />
        <div className="rounded-lg border bg-muted/30 p-3">
          <div className="flex items-center justify-between text-sm">
            <span className="flex items-center gap-2"><KeyRound className="size-4 text-muted-foreground" />{service.connected ? s('connected') : s('api_key_soon')}</span>
            {usage?.last_used_at && <span className="text-xs text-muted-foreground">{s('last_used')}: {formatDate(usage.last_used_at, locale)}</span>}
          </div>
          {hasUsage ? (
            <div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
              <DetailList className="sm:grid-cols-2" items={[
                { label: `${s('usage_month')} (${usage.month})`, value: `${formatNumber(usage.quantity, locale)}${unit ? ` ${unit}` : ''} · ${formatMoney(usage.cost, locale)}` },
                { label: s('usage_forecast'), value: formatMoney(usage.forecast, locale) },
              ]} />
              <div className="w-32"><UsageBars history={usage.history} /></div>
            </div>
          ) : <p className="mt-2 text-xs text-muted-foreground">{s('no_usage')}</p>}
        </div>
        {usage?.health && <DeliveryHealth health={usage.health} />}
        {service.pricing_model === 'per_use' && <CapForm projectSlug={projectSlug} service={service} />}
        {service.docs_url && (
          <Button asChild size="xs" variant="ghost" className="self-start">
            <a href={service.docs_url} target="_blank" rel="noopener"><BookOpen data-icon="inline-start" />{s('docs')}</a>
          </Button>
        )}
      </CardContent>
    </Card>
  );
}

function ReleaseTimeline({ releases }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <Card>
      <CardHeader><CardTitle>{t('portal.releases.title')}</CardTitle></CardHeader>
      <CardContent>
        {releases.length === 0 && <p className="text-sm text-muted-foreground">{t('portal.releases.none')}</p>}
        <ol className="relative ms-2 border-s ps-5">
          {releases.map((release) => (
            <li key={`${release.platform}-${release.version}-${release.released_at}`} className="relative pb-5 last:pb-0">
              <span className={`absolute -start-[1.6rem] top-1 grid size-3 place-items-center rounded-full border-2 ${release.channel === 'production' ? 'border-brass bg-brass' : 'border-border bg-card'}`} />
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="flex flex-wrap items-center gap-2 text-sm font-medium">
                  <Rocket className="size-3.5 text-muted-foreground" />
                  <span dir="ltr">{release.version}</span>
                  <Badge variant="outline">{t(`enums.release_platform.${release.platform}`)}</Badge>
                  <Badge variant="secondary">{t(`enums.release_channel.${release.channel}`)}</Badge>
                </span>
                <span className="text-xs text-muted-foreground">{formatDate(release.released_at, locale)}</span>
              </div>
              {release.notes && <p className="mt-1 whitespace-pre-line text-xs text-muted-foreground">{release.notes}</p>}
              {release.url && (
                <Button asChild size="xs" variant="ghost" className="mt-1">
                  <a href={release.url} target="_blank" rel="noopener">{t('portal.releases.open')}<ExternalLink data-icon="inline-end" /></a>
                </Button>
              )}
            </li>
          ))}
        </ol>
      </CardContent>
    </Card>
  );
}

function ChangeRequests({ project }) {
  const t = useT();
  const { locale } = useLocale();
  const c = (field) => t(`portal.changes.${field}`);
  const form = useForm({ title: '', description: '' });
  const base = `/app/projects/${project.slug}/changes`;

  function submit(event) {
    event.preventDefault();
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
      <Card>
        <CardHeader><CardTitle>{c('new')}</CardTitle></CardHeader>
        <CardContent>
          <p className="mb-4 text-sm text-muted-foreground">{c('lede')}</p>
          <form onSubmit={submit} className="flex flex-col gap-3">
            <FormField label={c('what')} htmlFor="change-title" error={form.errors.title}>
              <Input id="change-title" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
            </FormField>
            <FormField label={c('details')} htmlFor="change-description" error={form.errors.description}>
              <Textarea id="change-description" rows={5} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
            </FormField>
            <Button type="submit" className="self-start" disabled={form.processing}><Send data-icon="inline-start" />{c('send')}</Button>
          </form>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>{c('title')}</CardTitle></CardHeader>
        <CardContent>
          {project.change_requests.length === 0 && <p className="text-sm text-muted-foreground">{c('none')}</p>}
          <ul className="divide-y">
            {project.change_requests.map((change) => (
              <li key={change.id} className="flex flex-col gap-2 py-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="text-sm font-medium">{change.title}</div>
                    <div className="text-xs text-muted-foreground">{formatDate(change.created_at, locale)}</div>
                  </div>
                  <StatusBadge group="change_request_status" value={change.status} />
                </div>
                <p className="whitespace-pre-line text-sm text-muted-foreground">{change.description}</p>
                {change.quote_amount !== null && (
                  <div className="rounded-md border bg-muted/30 p-2 text-sm">
                    <span className="font-medium">{c('quote')}: {formatMoney(change.quote_amount, locale)}</span>
                    {change.quote_days && <span className="text-muted-foreground"> · {c('days').replace(':days', formatNumber(change.quote_days, locale))}</span>}
                    {change.quote_note && <p className="mt-1 text-xs text-muted-foreground">{change.quote_note}</p>}
                  </div>
                )}
                <div className="flex flex-wrap gap-1">
                  {change.status === 'quoted' && (
                    <>
                      <ConfirmAction href={`${base}/${change.id}/decide`} data={{ decision: 'approve' }} size="xs" title={c('approve')}>{c('approve')}</ConfirmAction>
                      <ConfirmAction href={`${base}/${change.id}/decide`} data={{ decision: 'reject' }} size="xs" variant="outline" title={c('reject')}>{c('reject')}</ConfirmAction>
                    </>
                  )}
                  {['submitted', 'quoted'].includes(change.status) && (
                    <ConfirmAction href={`${base}/${change.id}/withdraw`} size="xs" variant="ghost" title={c('withdraw')}>{c('withdraw')}</ConfirmAction>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>
    </div>
  );
}

export default function Project({ project, services, invoices, billing, tab }) {
  const t = useT();
  const { locale, dir } = useLocale();
  const p = (field) => t(`portal.project.${field}`);
  const poweredByBaydaq = project.type === 'powered_by_baydaq';
  const tabs = ['overview', 'files', 'services', 'releases', 'changes', 'billing'];
  const link = (url, label) => url && (
    <a href={url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-walnut underline-offset-4 hover:underline">
      {label}<ExternalLink className="size-3" />
    </a>
  );

  return (
    <PortalLayout title={project.name}>
      <Link href="/app" className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowRight className="size-4 ltr:rotate-180" />{p('back')}
      </Link>

      <div className="mb-6 flex items-start gap-4">
        <span className="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl border bg-card">
          {project.logo_url ? <img src={project.logo_url} alt="" className="size-full object-contain" /> : <BrandMark className="size-9 text-walnut" />}
        </span>
        <div className="min-w-0">
          <h1 className="text-2xl font-semibold">{project.name}</h1>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {poweredByBaydaq && <Badge className="bg-walnut text-ivory">{p('powered_by')}</Badge>}
            <StatusBadge group="project_status" value={project.status} />
          </div>
        </div>
      </div>

      <Tabs
        dir={dir}
        defaultValue={tabs.includes(tab) ? tab : 'overview'}
        onValueChange={(value) => window.history.replaceState(window.history.state, '', `?tab=${value}`)}
      >
        <TabsList className="mb-4 flex-wrap">
          {tabs.map((key) => <TabsTrigger key={key} value={key}>{t(`portal.project.tabs.${key}`)}</TabsTrigger>)}
        </TabsList>

        <TabsContent value="overview" className="grid gap-4 lg:grid-cols-3">
          <Card className="lg:col-span-2">
            <CardContent className="flex flex-col gap-6">
              {project.description && <p className="text-muted-foreground">{project.description}</p>}
              <div>
                <div className="mb-2 text-sm text-muted-foreground">{p('progress')}</div>
                <div className="flex items-center gap-3">
                  <Progress value={project.progress} className="h-2" />
                  <span className="text-sm font-medium">{formatPercent(project.progress, locale)}</span>
                </div>
              </div>
              <DetailList items={[
                poweredByBaydaq && { label: p('relationship'), value: project.relationship && t(`enums.project_relationship.${project.relationship}`) },
                poweredByBaydaq && project.relationship === 'partnership' && { label: p('share'), value: formatPercent(project.partnership_share, locale) },
                { label: p('started_at'), value: formatDate(project.started_at, locale) },
                { label: p('expected_launch_at'), value: formatDate(project.expected_launch_at, locale) },
                project.launched_at && { label: p('launched_at'), value: formatDate(project.launched_at, locale) },
                { label: p('app'), value: link(project.app_url, p('open')) },
              ]} />
            </CardContent>
          </Card>
          <Card className="lg:col-span-2">
            <CardHeader><CardTitle>{p('milestones')}</CardTitle></CardHeader>
            <CardContent>
              {(project.milestones ?? []).length === 0 && <p className="text-sm text-muted-foreground">{p('no_milestones')}</p>}
              <ol className="relative ms-2 border-s ps-5">
                {(project.milestones ?? []).map((milestone) => (
                  <li key={milestone.title} className="relative pb-4 last:pb-0">
                    <span className={`absolute -start-[1.6rem] top-1 size-3 rounded-full border-2 ${milestone.done_at ? 'border-brass bg-brass' : 'border-border bg-card'}`} />
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <span className={`text-sm font-medium ${milestone.done_at ? '' : 'text-muted-foreground'}`}>{milestone.title}</span>
                      <span className="text-xs text-muted-foreground">{milestone.done_at ? `${p('done')} · ${formatDate(milestone.done_at, locale)}` : milestone.due_date ? `${p('due')} · ${formatDate(milestone.due_date, locale)}` : ''}</span>
                    </div>
                    {milestone.description && <p className="mt-0.5 text-xs text-muted-foreground">{milestone.description}</p>}
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>{p('team')}</CardTitle></CardHeader>
            <CardContent>
              {project.team.length === 0 && <p className="text-sm text-muted-foreground">{p('no_team')}</p>}
              <ul className="divide-y">
                {project.team.map((member) => (
                  <li key={`${member.name}-${member.role}`} className="py-2">
                    <div className="text-sm font-medium">{member.name}</div>
                    {member.role && <div className="text-xs text-muted-foreground">{member.role}</div>}
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="files">
          <Card>
            <CardContent className="flex flex-col gap-3">
              {project.drive_url && (
                <Button asChild variant="outline" className="self-start">
                  <a href={project.drive_url} target="_blank" rel="noopener"><FolderOpen data-icon="inline-start" />{p('drive')}</a>
                </Button>
              )}
              {project.files.length === 0 && <p className="text-sm text-muted-foreground">{p('no_files')}</p>}
              <ul className="divide-y">
                {project.files.map((file) => (
                  <li key={file.url} className="flex items-center justify-between gap-3 py-3">
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-medium">{file.title}</span>
                      <span className="text-xs text-muted-foreground">{t(`enums.file_category.${file.category}`)}</span>
                    </span>
                    <Button asChild size="xs" variant="ghost">
                      <a href={file.url} target="_blank" rel="noopener">{p('open')}<ExternalLink data-icon="inline-end" /></a>
                    </Button>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="services">
          {services.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">{t('portal.services.none')}</CardContent></Card>}
          <div className="grid gap-4 md:grid-cols-2">
            {services.map((service) => <ServiceCard key={service.id} service={service} projectSlug={project.slug} />)}
          </div>
        </TabsContent>

        <TabsContent value="releases">
          <ReleaseTimeline releases={project.releases ?? []} />
        </TabsContent>

        <TabsContent value="changes">
          <ChangeRequests project={project} />
        </TabsContent>

        <TabsContent value="billing" className="flex flex-col gap-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <StatCard icon={Wallet} label={t('portal.billing.due')} value={formatMoney(billing.due, locale)} />
            <StatCard icon={AlertTriangle} tone="bg-red-100 text-red-700" label={t('portal.billing.overdue')} value={formatMoney(billing.overdue, locale)} />
          </div>
          <InvoiceTable invoices={invoices} />
          <p className="text-sm text-muted-foreground">{t('portal.billing.how_to_pay')}</p>
        </TabsContent>
      </Tabs>
    </PortalLayout>
  );
}
