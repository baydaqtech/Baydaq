import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Option, Paginated } from '@/types/resources';

interface MemberRow {
  id: number; number: string; name: string; phone: string; is_active: boolean;
  current: { plan: string; starts_on: string; ends_on: string; sessions_left: number | null } | null;
  [key: string]: unknown;
}

export function MemberFields({ form, companies }: { form: ReturnType<typeof useForm<{ company_id?: number | string; name: string; phone: string; email: string; gender: string; birth_date: string; note: string }>>; companies?: Option[] }) {
  const t = useT();
  const f = (field: string) => t(`subscriptions.members.fields.${field}`);
  const genders = [{ value: 'male', label: t('subscriptions.members.genders.male') }, { value: 'female', label: t('subscriptions.members.genders.female') }];

  return (
    <>
      {companies && companies.length > 1 && <FormField className="sm:col-span-2" label={f('company')}><SelectInput value={form.data.company_id ?? ''} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>}
      <FormField label={f('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
      <FormField label={f('phone')} error={form.errors.phone}><Input dir="ltr" type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></FormField>
      <FormField label={f('email')} error={form.errors.email}><Input dir="ltr" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
      <FormField label={f('gender')} error={form.errors.gender}><SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} options={genders} emptyLabel="—" /></FormField>
      <FormField label={f('birth_date')} error={form.errors.birth_date}><Input dir="ltr" type="date" value={form.data.birth_date} onChange={(event) => form.setData('birth_date', event.target.value)} /></FormField>
      <FormField label={f('note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
    </>
  );
}

function NewMember({ companies }: { companies: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ company_id: companies[0]?.value ?? '', name: '', phone: '', email: '', gender: '', birth_date: '', note: '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, gender: data.gender || null, birth_date: data.birth_date || null, email: data.email || null }));
    form.post(url('/subscriptions/members'));
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><Plus data-icon="inline-start" />{t('subscriptions.members.new')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-w-xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('subscriptions.members.new')}</DialogTitle></DialogHeader>
          <MemberFields form={form} companies={companies} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function MembersIndex({ members, filters: initialFilters, companies }: { members: Paginated<MemberRow>; filters: Filters; companies: Option[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`subscriptions.members.fields.${field}`);

  return (
    <SuiteLayout title={t('subscriptions.members.title')}>
      <PageHeader title={t('subscriptions.members.title')} actions={can('subscriptions.members.create') && <NewMember companies={companies} />} />
      <FilterBar filters={filters} setFilter={setFilter} selects={[{ key: 'status', label: f('status'), options: [{ value: 'active', label: t('subscriptions.members.filters.active') }, { value: 'lapsed', label: t('subscriptions.members.filters.lapsed') }] }]} />
      <DataTable<MemberRow>
        rows={members}
        rowHref={(member) => url(`/subscriptions/members/${member.id}`)}
        empty={t('subscriptions.members.none')}
        columns={[
          { key: 'number', label: f('number'), render: (member) => <span dir="ltr" className="font-mono text-xs">{member.number}</span> },
          { key: 'name', label: f('name'), render: (member) => <span className={member.is_active ? 'font-medium' : 'font-medium text-muted-foreground line-through'}>{member.name}</span> },
          { key: 'phone', label: f('phone'), render: (member) => <span dir="ltr">{member.phone}</span> },
          { key: 'plan', label: f('plan'), render: (member) => (member.current ? <span>{member.current.plan}<span className="block text-xs text-muted-foreground" dir="ltr">{member.current.starts_on} → {member.current.ends_on}</span></span> : <span className="text-muted-foreground">{t('subscriptions.members.no_membership')}</span>) },
          { key: 'sessions', label: '', render: (member) => (member.current?.sessions_left != null ? t('subscriptions.members.sessions_left', { count: member.current.sessions_left }) : '') },
        ]}
      />
    </SuiteLayout>
  );
}
