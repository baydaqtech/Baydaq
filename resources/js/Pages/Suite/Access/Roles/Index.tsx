import { Link } from '@inertiajs/react';
import { Lock, Pencil, Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { AccessRoleResource } from '@/types/suite';

type Row = AccessRoleResource & Record<string, unknown>;

export default function RolesIndex({ roles }: { roles: AccessRoleResource[] }) {
  const t = useT();
  const { locale } = useLocale();
  const { can } = useAccess();
  const url = useSuiteUrl();

  return (
    <SuiteLayout title={t('suite.access.roles')}>
      <PageHeader
        title={t('suite.access.roles')}
        actions={can('suite.access.create') && (
          <Button asChild size="sm"><Link href={url('/access/roles/create')}><Plus data-icon="inline-start" />{t('suite.access.new_role')}</Link></Button>
        )}
      />
      <DataTable<Row>
        rows={roles as Row[]}
        empty={t('suite.access.no_roles')}
        columns={[
          { key: 'name', label: t('suite.access.fields.role'), render: (role) => (
            <span className="flex items-center gap-2 font-medium">
              {role.name}
              {role.is_locked && <Badge variant="outline" className="gap-1 border-transparent bg-muted text-muted-foreground"><Lock className="size-3" />{t('suite.access.locked')}</Badge>}
            </span>
          ) },
          { key: 'permissions', label: t('suite.access.permissions'), render: (role) => (
            <span className="text-muted-foreground">{role.permissions?.includes('*') ? '∗' : formatNumber(role.permissions?.length ?? 0, locale)}</span>
          ) },
          { key: 'holders', label: t('suite.access.members'), render: (role) => t('suite.access.holders', { count: formatNumber(role.assignments_count ?? 0, locale) }) },
          { key: 'actions', label: '', className: 'text-end', render: (role) => (
            can('suite.access.update') && !role.is_locked && (
              <Button asChild size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Link href={url(`/access/roles/${role.id}/edit`)}><Pencil /></Link></Button>
            )
          ) },
        ]}
      />
    </SuiteLayout>
  );
}
