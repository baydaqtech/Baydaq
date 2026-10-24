import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import FormActions from '@/components/platform/FormActions';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import ScopePicker, { type ScopeLevel } from '@/components/suite/ScopePicker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';
import type { AccessRoleResource } from '@/types/suite';

interface Props {
  users: Option[];
  roles: AccessRoleResource[];
  levels: Option[];
  user_id: number | null;
}

function InviteDialog() {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ name: '', email: '', locale: 'ar' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(url('/access/members/invite'), { onSuccess: () => setOpen(false) });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline"><UserPlus data-icon="inline-start" />{t('suite.access.invite')}</Button>
      </DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>{t('suite.access.invite')}</DialogTitle>
            <DialogDescription>{t('suite.access.invite_text')}</DialogDescription>
          </DialogHeader>
          <FormField label={t('suite.access.name')} htmlFor="invite-name" error={form.errors.name}>
            <Input id="invite-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
          </FormField>
          <FormField label={t('suite.access.email')} htmlFor="invite-email" error={form.errors.email}>
            <Input id="invite-email" type="email" dir="ltr" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
          </FormField>
          <FormField label={t('suite.access.locale')} htmlFor="invite-locale" error={form.errors.locale}>
            <SelectInput
              id="invite-locale"
              value={form.data.locale}
              onChange={(value) => form.setData('locale', value)}
              options={[{ value: 'ar', label: t('common.language.ar') }, { value: 'en', label: t('common.language.en') }]}
            />
          </FormField>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}>{t('suite.access.invite')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function MemberForm({ users, roles, levels, user_id }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const form = useForm<{ user_id: number | ''; access_role_id: number | ''; level: ScopeLevel | ''; node_id: number | '' }>({
    user_id: user_id ?? '',
    access_role_id: roles[0]?.id ?? '',
    level: '',
    node_id: '',
  });
  const { data, setData, errors, processing } = form;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(url('/access/members'));
  }

  return (
    <SuiteLayout title={t('suite.access.assign')}>
      <PageHeader title={t('suite.access.assign')} actions={<InviteDialog />} />
      <Card className="max-w-3xl">
        <CardContent>
          <form onSubmit={submit} className="flex flex-col gap-4">
            <FormField label={t('suite.access.fields.user')} htmlFor="user_id" error={errors.user_id}>
              <SelectInput id="user_id" value={data.user_id} onChange={(value) => setData('user_id', Number(value))} options={users} />
            </FormField>
            <FormField label={t('suite.access.fields.role')} htmlFor="access_role_id" error={errors.access_role_id} hint={roles.length === 0 ? t('suite.access.no_roles') : undefined}>
              <SelectInput
                id="access_role_id"
                value={data.access_role_id}
                onChange={(value) => setData('access_role_id', Number(value))}
                options={roles.map((role) => ({ value: role.id, label: role.name }))}
              />
            </FormField>
            <ScopePicker
              level={data.level}
              nodeId={data.node_id}
              levels={levels}
              errors={{ level: errors.level, node_id: errors.node_id }}
              onChange={(level, nodeId) => { setData((current) => ({ ...current, level, node_id: nodeId })); }}
            />
            <FormActions processing={processing} cancelHref={url('/access/members')} saveLabel={t('suite.access.assign')} />
          </form>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
