import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, BookOpen, ExternalLink, FolderOpen, KeyRound, Wallet } from 'lucide-react';
import BrandMark from '@/components/platform/BrandMark';
import DetailList from '@/components/platform/DetailList';
import InvoiceTable from '@/components/platform/InvoiceTable';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PortalLayout from '@/layouts/PortalLayout';
import { formatDate, formatMoney, formatNumber, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { serviceIcon } from '@/lib/serviceIcons';

function ServiceCard({ service }) {
  const t = useT();
  const { locale } = useLocale();
  const s = (field) => t(`portal.services.${field}`);
  const Icon = serviceIcon(service.icon);
  const unit = service.unit ? t(`common.units.${service.unit}`) : null;

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
          { label: s('pricing_model'), value: t(`enums.pricing_model.${service.pricing_model}`) },
          { label: s('started_at'), value: formatDate(service.started_at, locale) },
        ]} />
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2 text-sm">
          <span className="flex items-center gap-2"><KeyRound className="size-4 text-muted-foreground" />{s('api_key')}</span>
          <span className="text-muted-foreground">{s('api_key_soon')}</span>
        </div>
        {service.docs_url && (
          <Button asChild size="sm" variant="outline" className="self-start">
            <a href={service.docs_url} target="_blank" rel="noopener"><BookOpen data-icon="inline-start" />{s('docs')}</a>
          </Button>
        )}
      </CardContent>
    </Card>
  );
}

export default function Project({ project, services, invoices, billing, tab }) {
  const t = useT();
  const { locale, dir } = useLocale();
  const p = (field) => t(`portal.project.${field}`);
  const poweredByBaydaq = project.type === 'powered_by_baydaq';
  const tabs = ['overview', 'files', 'services', 'billing'];
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
        <TabsList className="mb-4">
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
            {services.map((service) => <ServiceCard key={service.id} service={service} />)}
          </div>
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
