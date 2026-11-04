import { Link } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Paginated } from '@/types/resources';
import type { MemberResource } from '@/types/suite';

interface Props {
  members: Paginated<MemberResource>;
  filters: Filters;
}

export default function MembersIndex({ members, filters: initialFilters }: Props) {
  const t = useT();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);

  return (
    <SuiteLayout title={t('suite.access.members')}>
      <PageHeader
        title={t('suite.access.members')}
        actions={can('suite.access.create') && (
          <Button asChild size="sm"><Link href={url('/access/members/create')}><Plus data-icon="inline-start" />{t('suite.access.assign')}</Link></Button>
        )}
      />
      <FilterBar filters={filters} setFilter={setFilter} />
      <DataTable<MemberResource>
        rows={members}
        empty={t('suite.access.members_none')}
        columns={[
          { key: 'name', label: t('suite.access.fields.user'), render: (member) => (
            <span>
              <span className="block font-medium">{member.name}</span>
              <span className="block text-xs text-muted-foreground" dir="ltr">{member.email}</span>
              {member.pending && <Badge variant="outline" className="mt-1 border-transparent bg-amber-100 text-amber-800">{t('suite.access.pending')}</Badge>}
            </span>
          ) },
          { key: 'assignments', label: t('suite.access.roles'), render: (member) => (
            member.assignments.length === 0
              ? <span className="text-muted-foreground">{t('suite.access.none')}</span>
              : (
                <ul className="flex flex-wrap gap-1.5">
                  {member.assignments.map((assignment) => (
                    <li key={assignment.id} className="inline-flex items-center gap-1 rounded-full border bg-card py-0.5 ps-2.5 pe-1 text-xs">
                      <span className="font-medium">{assignment.role}</span>
                      <span className="text-muted-foreground">· {assignment.node ?? t('suite.scope.whole_account')}</span>
                      {can('suite.access.delete') && (
                        <ConfirmAction
                          href={url(`/access/assignments/${assignment.id}`)}
                          method="delete"
                          variant="ghost"
                          size="icon-xs"
                          title={t('suite.access.revoke_title')}
                          description={`${member.name} — ${assignment.role}`}
                          confirmLabel={t('suite.access.revoke')}
                        >
                          <X className="size-3" />
                        </ConfirmAction>
                      )}
                    </li>
                  ))}
                </ul>
              )
          ) },
        ]}
      />
    </SuiteLayout>
  );
}
