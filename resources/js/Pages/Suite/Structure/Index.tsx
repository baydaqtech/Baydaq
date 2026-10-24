import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Building2, MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import StatusBadge from '@/components/platform/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { BranchResource, CompanyResource, OrganizationResource } from '@/types/suite';

function OrganizationDialog({ organization }: { organization?: OrganizationResource }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ name_ar: organization?.name_ar ?? '', name_en: organization?.name_en ?? '', slug: organization?.slug ?? '' });
  const f = (field: string) => t(`suite.structure.fields.${field}`);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset(); } };
    organization ? form.put(url(`/structure/organizations/${organization.id}`), options) : form.post(url('/structure/organizations'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {organization
          ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button>
          : <Button size="sm" variant="outline"><Plus data-icon="inline-start" />{t('suite.structure.new_organization')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{organization ? organization.name : t('suite.structure.new_organization')}</DialogTitle></DialogHeader>
          <FormField label={f('name_ar')} htmlFor="org-name-ar" error={form.errors.name_ar}>
            <Input id="org-name-ar" dir="rtl" value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} />
          </FormField>
          <FormField label={f('name_en')} htmlFor="org-name-en" error={form.errors.name_en}>
            <Input id="org-name-en" dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} />
          </FormField>
          <FormField label={f('slug')} htmlFor="org-slug" error={form.errors.slug} hint={t('common.actions.optional')}>
            <Input id="org-slug" dir="ltr" value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} />
          </FormField>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CompanyCard({ company }: { company: CompanyResource }) {
  const t = useT();
  const { can } = useAccess();
  const url = useSuiteUrl();
  const branches = company.branches ?? [];

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-2">
        <div className="min-w-0">
          <CardTitle className="flex items-center gap-2"><Building2 className="size-4 text-brass" />{company.name}</CardTitle>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            {company.cr_number && <span dir="ltr">CR {company.cr_number}</span>}
            {company.vat_number && <span dir="ltr">VAT {company.vat_number}</span>}
            <span>{t('suite.structure.branches_count', { count: branches.length })}</span>
            <StatusBadge group="structure" value={company.status} file="suite" />
          </div>
        </div>
        <div className="flex shrink-0 gap-1">
          {can('suite.structure.create') && (
            <Button asChild size="sm" variant="outline"><Link href={url(`/structure/companies/${company.id}/branches/create`)}><Plus data-icon="inline-start" />{t('suite.structure.new_branch')}</Link></Button>
          )}
          {can('suite.structure.update') && (
            <Button asChild size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Link href={url(`/structure/companies/${company.id}/edit`)}><Pencil /></Link></Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <DataTable<BranchResource & Record<string, unknown>>
          rows={branches as (BranchResource & Record<string, unknown>)[]}
          columns={[
            { key: 'code', label: t('suite.structure.fields.code'), render: (branch) => <span className="font-mono text-xs" dir="ltr">{branch.code}</span> },
            { key: 'name', label: t('suite.structure.branches'), render: (branch) => (
              <span className="flex items-center gap-2 font-medium">
                <MapPin className="size-3.5 text-muted-foreground" />{branch.name}
                {branch.is_head_office && <Badge variant="outline" className="border-transparent bg-brass/20 text-walnut">{t('suite.structure.head_office')}</Badge>}
              </span>
            ) },
            { key: 'city', label: t('suite.structure.fields.city_ar'), render: (branch) => branch.city ?? '—' },
            { key: 'status', label: t('suite.structure.fields.status'), render: (branch) => <StatusBadge group="structure" value={branch.status} file="suite" /> },
            { key: 'actions', label: '', className: 'text-end', render: (branch) => (
              <span className="inline-flex gap-1">
                {can('suite.structure.update') && (
                  <Button asChild size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Link href={url(`/structure/branches/${branch.id}/edit`)}><Pencil /></Link></Button>
                )}
                {can('suite.structure.delete') && !branch.is_head_office && (
                  <ConfirmAction
                    href={url(`/structure/branches/${branch.id}`)}
                    method="delete"
                    variant="ghost"
                    size="icon-sm"
                    title={t('suite.structure.delete_branch')}
                    description={t('suite.structure.delete_branch_text')}
                    confirmLabel={t('suite.structure.delete_branch')}
                  >
                    <Trash2 className="text-destructive" />
                  </ConfirmAction>
                )}
              </span>
            ) },
          ]}
        />
      </CardContent>
    </Card>
  );
}

export default function StructureIndex({ organizations }: { organizations: OrganizationResource[] }) {
  const t = useT();
  const { can, isOwner } = useAccess();
  const url = useSuiteUrl();

  return (
    <SuiteLayout title={t('suite.structure.title')}>
      <PageHeader
        title={t('suite.structure.title')}
        actions={(
          <>
            {isOwner && <OrganizationDialog />}
            {can('suite.structure.create') && (
              <Button asChild size="sm"><Link href={url('/structure/companies/create')}><Plus data-icon="inline-start" />{t('suite.structure.new_company')}</Link></Button>
            )}
          </>
        )}
      />
      {organizations.length === 0 && (
        <Card><CardContent className="py-10 text-center text-muted-foreground">{t('suite.structure.empty')}</CardContent></Card>
      )}
      <div className="flex flex-col gap-8">
        {organizations.map((organization) => (
          <section key={organization.id} aria-labelledby={`org-${organization.id}`}>
            <div className="mb-3 flex items-center gap-2">
              <h2 id={`org-${organization.id}`} className="text-base font-semibold">{organization.name}</h2>
              <span className="text-xs text-muted-foreground">{t('suite.scope.organization')}</span>
              {isOwner && <OrganizationDialog organization={organization} />}
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
              {(organization.companies ?? []).map((company) => <CompanyCard key={company.id} company={company} />)}
            </div>
          </section>
        ))}
      </div>
    </SuiteLayout>
  );
}
