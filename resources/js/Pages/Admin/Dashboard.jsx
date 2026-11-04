import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Banknote, Building2, FolderKanban, Inbox, Wallet } from 'lucide-react';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout';
import { useCan } from '@/lib/auth';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export default function Dashboard({ stats, recentRequests }) {
  const t = useT();
  const can = useCan();
  const { locale } = useLocale();
  const { user } = usePage().props.auth;

  return (
    <AdminLayout title={t('admin.dashboard.title')}>
      <p className="mb-5 text-muted-foreground">{t('admin.dashboard.welcome', { name: user.name })}</p>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard icon={Building2} label={t('admin.dashboard.clients')} value={formatNumber(stats.clients, locale)} href={can('clients.view', 'clients.manage') ? '/admin/clients' : undefined} />
        <StatCard icon={FolderKanban} label={t('admin.dashboard.projects')} value={formatNumber(stats.projects, locale)} href={can('projects.view', 'projects.manage') ? '/admin/projects' : undefined} />
        <StatCard icon={Inbox} label={t('admin.dashboard.new_requests')} value={formatNumber(stats.new_requests, locale)} href={can('requests.manage') ? '/admin/requests?status=new' : undefined} />
        <StatCard icon={Wallet} label={t('admin.dashboard.total_due')} value={formatMoney(stats.total_due, locale)} href={can('invoices.view', 'invoices.manage') ? '/admin/invoices' : undefined} />
        <StatCard icon={AlertTriangle} tone="bg-red-100 text-red-700" label={t('admin.dashboard.overdue')} value={formatMoney(stats.overdue, locale)} href={can('invoices.view', 'invoices.manage') ? '/admin/invoices?status=overdue' : undefined} />
        <StatCard icon={Banknote} tone="bg-emerald-100 text-emerald-700" label={t('admin.dashboard.paid_this_month')} value={formatMoney(stats.paid_this_month, locale)} />
      </div>

      {can('requests.manage') && (
        <Card className="mt-6">
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{t('admin.dashboard.recent_requests')}</CardTitle>
            <Link href="/admin/requests" className="text-sm text-muted-foreground hover:text-foreground">{t('admin.dashboard.view_all')}</Link>
          </CardHeader>
          <CardContent>
            {recentRequests.length === 0 && <p className="text-sm text-muted-foreground">{t('admin.dashboard.no_requests')}</p>}
            <ul className="divide-y">
              {recentRequests.map((request) => (
                <li key={request.id}>
                  <Link href={`/admin/requests/${request.id}`} className="flex items-center justify-between gap-3 py-3 hover:text-walnut">
                    <span className="min-w-0">
                      <span className="block truncate font-medium">{request.name}</span>
                      <span className="block truncate text-xs text-muted-foreground">{request.company ?? '—'} · {formatDate(request.created_at, locale)}</span>
                    </span>
                    <StatusBadge group="request_type" value={request.type} />
                  </Link>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}
    </AdminLayout>
  );
}
