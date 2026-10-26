import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Plus, ShieldAlert } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface InsuranceMember {
  id: number; employee_id: number; relation: string; name: string | null; class: string; card_number: string | null;
  starts_on: string; ends_on: string | null; premium: string | null; status: string;
}

interface Policy {
  id: number; company: string | null; company_id: number; insurer: string; policy_number: string | null; starts_on: string; ends_on: string;
  notes: string | null; renewal_due: boolean; can_update: boolean; members: (InsuranceMember & { employee: string; can_update: boolean })[];
}

type EmployeeOption = Option & { company_id: number };

interface Props {
  policies: Policy[];
  uninsured: { id: number; name: string; number: string; company_id: number }[];
  companies: Option[];
  employees: EmployeeOption[];
  relations: string[];
}

function PolicyForm({ companies, onDone }: { companies: Option[]; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const i = (key: string) => t(`hr.insurance.${key}`);
  const form = useForm({ company_id: companies[0]?.value ?? '', insurer: '', policy_number: '', starts_on: '', ends_on: '', notes: '' });

  return (
    <Card className="mb-4"><CardContent className="py-4">
      <form className="grid gap-3 sm:grid-cols-3" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/insurance'), { preserveScroll: true, onSuccess: onDone }); }}>
        <FormField label={i('company')} error={form.errors.company_id}><SelectInput value={String(form.data.company_id)} onChange={(value) => form.setData('company_id', Number(value))} options={companies} /></FormField>
        <FormField label={i('insurer')} error={form.errors.insurer}><Input value={form.data.insurer} onChange={(event) => form.setData('insurer', event.target.value)} /></FormField>
        <FormField label={i('policy_number')} error={form.errors.policy_number}><Input dir="ltr" value={form.data.policy_number} onChange={(event) => form.setData('policy_number', event.target.value)} /></FormField>
        <FormField label={i('starts_on')} error={form.errors.starts_on}><Input type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
        <FormField label={i('ends_on')} error={form.errors.ends_on}><Input type="date" dir="ltr" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} /></FormField>
        <FormField label={i('notes')} error={form.errors.notes}><Input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></FormField>
        <div className="sm:col-span-3"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></div>
      </form>
    </CardContent></Card>
  );
}

function MemberForm({ policy, employees, relations }: { policy: Policy; employees: EmployeeOption[]; relations: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const i = (key: string) => t(`hr.insurance.${key}`);
  const options = employees.filter((employee) => employee.company_id === policy.company_id);
  const form = useForm({ employee_id: options[0]?.value ?? '', relation: 'self', name: '', class: 'B', card_number: '', starts_on: policy.starts_on, ends_on: policy.ends_on, premium: '' });

  if (options.length === 0) {
    return null;
  }

  return (
    <form className="mt-3 grid gap-2 rounded-md border p-3 sm:grid-cols-4" onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/insurance/${policy.id}/members`), { preserveScroll: true, onSuccess: () => form.reset('name', 'card_number', 'premium') }); }}>
      <FormField label={i('employee')} error={form.errors.employee_id} className="sm:col-span-2"><SelectInput value={String(form.data.employee_id)} onChange={(value) => form.setData('employee_id', Number(value))} options={options} /></FormField>
      <FormField label={i('relation')} error={form.errors.relation}><SelectInput value={form.data.relation} onChange={(value) => form.setData('relation', value)} options={relations.map((value) => ({ value, label: t(`hr.insurance.relations.${value}`) }))} /></FormField>
      {form.data.relation !== 'self' && <FormField label={i('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>}
      <FormField label={i('class')} error={form.errors.class}><Input dir="ltr" value={form.data.class} onChange={(event) => form.setData('class', event.target.value)} /></FormField>
      <FormField label={i('card_number')} error={form.errors.card_number}><Input dir="ltr" value={form.data.card_number} onChange={(event) => form.setData('card_number', event.target.value)} /></FormField>
      <FormField label={i('starts_on')} error={form.errors.starts_on}><Input type="date" dir="ltr" value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} /></FormField>
      <FormField label={i('premium')} error={form.errors.premium}><Input type="number" min="0" step="0.01" dir="ltr" value={form.data.premium} onChange={(event) => form.setData('premium', event.target.value)} /></FormField>
      <div className="sm:col-span-4"><Button type="submit" size="sm" disabled={form.processing}><Plus data-icon="inline-start" />{i('add_member')}</Button></div>
    </form>
  );
}

/** Medical insurance policies per company, who is covered, renewals and the uninsured. */
export default function InsuranceIndex({ policies, uninsured, companies, employees, relations }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const i = (key: string) => t(`hr.insurance.${key}`);
  const [adding, setAdding] = useState(false);

  return (
    <SuiteLayout title={i('title')}>
      <PageHeader title={i('title')} description={i('lede')} actions={companies.length > 0 && <Button onClick={() => setAdding(!adding)}><Plus data-icon="inline-start" />{i('new_policy')}</Button>} />
      {adding && <PolicyForm companies={companies} onDone={() => setAdding(false)} />}
      <Card className="mb-4">
        <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldAlert className="size-4" />{i('uninsured')} ({uninsured.length})</CardTitle></CardHeader>
        <CardContent className="flex flex-wrap gap-2 text-sm">
          {uninsured.length === 0 ? <p className="text-muted-foreground">{i('all_insured')}</p> : uninsured.map((employee) => (
            <Link key={employee.id} href={url(`/hr/employees/${employee.id}`)}><Badge variant="outline">{employee.name} · <span dir="ltr">{employee.number}</span></Badge></Link>
          ))}
        </CardContent>
      </Card>
      {policies.length === 0 && <p className="text-sm text-muted-foreground">{i('no_policies')}</p>}
      <div className="flex flex-col gap-4">
        {policies.map((policy) => (
          <Card key={policy.id}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                {policy.insurer}{policy.policy_number && <span className="text-sm font-normal text-muted-foreground" dir="ltr">{policy.policy_number}</span>}
                <span className="text-sm font-normal text-muted-foreground">· {policy.company} · {formatDate(policy.starts_on, locale)} – {formatDate(policy.ends_on, locale)}</span>
                {policy.renewal_due && <Badge variant="destructive">{i('renewal_due')}</Badge>}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-sm">
              <ul className="divide-y">
                {policy.members.map((member) => (
                  <li key={member.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5">
                    <span>
                      <span className="font-medium">{member.employee}</span>
                      {member.relation !== 'self' && <> · {t(`hr.insurance.relations.${member.relation}`)}: {member.name}</>}
                      {' '}<Badge variant="outline">{member.class}</Badge>
                      {member.card_number && <span className="text-muted-foreground" dir="ltr"> {member.card_number}</span>}
                      {member.premium && <span className="text-muted-foreground"> · {formatMoney(member.premium, locale)}</span>}
                    </span>
                    {member.can_update && <ConfirmAction href={url(`/hr/insurance/members/${member.id}`)} method="delete" size="xs" variant="ghost" title={i('remove')}>{i('remove')}</ConfirmAction>}
                  </li>
                ))}
              </ul>
              {policy.can_update && <MemberForm policy={policy} employees={employees} relations={relations} />}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
