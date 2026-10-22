import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Wallet } from 'lucide-react';
import BrandMark from '@/components/platform/BrandMark';
import InvoiceTable from '@/components/platform/InvoiceTable';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import PortalLayout from '@/layouts/PortalLayout';
import { formatMoney, formatNumber, formatPercent } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

function ProjectCard({ project }) {
  const t = useT();
  const { locale } = useLocale();

  return (
    <Link href={`/app/projects/${project.slug}`} className="group block">
      <Card className="h-full transition-colors group-hover:border-brass/60">
        <CardContent className="flex flex-col gap-4">
          <div className="flex items-start gap-3">
            <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg border bg-secondary">
              {project.logo_url ? <img src={project.logo_url} alt="" className="size-full object-contain" /> : <BrandMark className="size-7 text-walnut" />}
            </span>
            <div className="min-w-0 flex-1">
              <h3 className="truncate font-semibold">{project.name}</h3>
              <div className="mt-1 flex flex-wrap gap-1.5">
                {project.type === 'powered_by_baydaq' && <Badge className="bg-walnut text-ivory">{t('portal.project.powered_by')}</Badge>}
                <StatusBadge group="project_status" value={project.status} />
              </div>
            </div>
          </div>
          {project.description && <p className="line-clamp-2 text-sm text-muted-foreground">{project.description}</p>}
          <div className="mt-auto flex items-center gap-3">
            <Progress value={project.progress} className="h-1.5" />
            <span className="text-xs text-muted-foreground">{formatPercent(project.progress, locale)}</span>
          </div>
          <div className="text-xs text-muted-foreground">{t('portal.home.services_count', { count: formatNumber(project.services_count ?? 0, locale) })}</div>
        </CardContent>
      </Card>
    </Link>
  );
}

export default function Home({ projects, invoices, billing }) {
  const t = useT();
  const { locale } = useLocale();
  const { user } = usePage().props.auth;

  return (
    <PortalLayout title={t('portal.home.title')}>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold">{t('portal.home.welcome', { name: user.name })}</h1>
        <p className="mt-1 text-muted-foreground">{t('portal.home.lede')}</p>
      </div>

      <section aria-labelledby="projects-title">
        <h2 id="projects-title" className="mb-3 text-lg font-semibold">{t('portal.home.title')}</h2>
        {projects.length === 0
          ? <Card><CardContent className="py-10 text-center text-muted-foreground">{t('portal.home.no_projects')}</CardContent></Card>
          : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {projects.map((project) => <ProjectCard key={project.slug} project={project} />)}
            </div>
          )}
      </section>

      <section aria-labelledby="billing-title" className="mt-10">
        <h2 id="billing-title" className="mb-3 text-lg font-semibold">{t('portal.billing.title')}</h2>
        <div className="mb-4 grid gap-3 sm:grid-cols-2">
          <StatCard icon={Wallet} label={t('portal.billing.due')} value={formatMoney(billing.due, locale)} />
          <StatCard icon={AlertTriangle} tone="bg-red-100 text-red-700" label={t('portal.billing.overdue')} value={formatMoney(billing.overdue, locale)} />
        </div>
        <InvoiceTable invoices={invoices} showProject />
        <p className="mt-3 text-sm text-muted-foreground">{t('portal.billing.how_to_pay')}</p>
      </section>
    </PortalLayout>
  );
}
