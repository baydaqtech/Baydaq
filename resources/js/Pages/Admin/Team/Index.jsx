import { Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { formatDate } from '@/lib/format';
import { useCopy, useLocale, useT } from '@/lib/i18n';

export default function TeamIndex({ members }) {
  const t = useT();
  const { locale } = useLocale();
  const resend = useForm({});
  const f = (field) => t(`admin.team.fields.${field}`);
  // Permission names contain dots (clients.view), so labels come straight from the map.
  const permissionLabels = useCopy('admin.team.permissions');

  return (
    <AdminLayout title={t('admin.team.title')}>
      <PageHeader actions={<Button asChild><Link href="/admin/team/create"><Plus data-icon="inline-start" />{t('admin.team.new')}</Link></Button>} />
      <DataTable
        rows={members}
        rowHref={(member) => (member.is_system_admin ? undefined : `/admin/team/${member.id}/edit`)}
        columns={[
          { key: 'name', label: f('name'), render: (member) => (
            <span><span className="font-medium">{member.name}</span><span className="block text-xs text-muted-foreground" dir="ltr">{member.email}</span></span>
          ) },
          { key: 'role', label: f('role'), render: (member) => (
            <Badge variant="outline" className={member.is_system_admin ? 'border-transparent bg-walnut text-ivory' : ''}>
              {member.is_system_admin ? t('admin.team.system_admin') : t('admin.team.admin')}
            </Badge>
          ) },
          { key: 'permissions', label: f('permissions'), className: 'max-w-sm whitespace-normal', render: (member) => (
            member.is_system_admin ? '—' : (
              <span className="flex flex-wrap gap-1">
                {member.permissions?.map((permission) => <Badge key={permission} variant="secondary" className="font-normal">{permissionLabels[permission] ?? permission}</Badge>)}
              </span>
            )
          ) },
          { key: 'last_login', label: f('last_login'), render: (member) => (
            member.pending ? (
              <Button size="xs" variant="ghost" disabled={resend.processing} onClick={() => resend.post(`/admin/team/${member.id}/invite`, { preserveScroll: true })}>
                {t('admin.team.resend')}
              </Button>
            ) : (member.last_login_at ? formatDate(member.last_login_at, locale) : '—')
          ) },
        ]}
      />
    </AdminLayout>
  );
}
