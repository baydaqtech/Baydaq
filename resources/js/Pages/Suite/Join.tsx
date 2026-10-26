import { Head, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import PlatformShell from '@/layouts/PlatformShell';
import { useT } from '@/lib/i18n';

interface Props {
  token: string;
  branch: { name: string; company: string };
  theme: { app_name: string | null; logo_url: string | null } | null;
}

/** What a branch's sign-up QR code opens: a client registers with their phone, no account needed. */
export default function Join({ token, branch, theme }: Props) {
  const t = useT();
  const j = (field: string, replace?: Record<string, string>) => t(`suite.join.${field}`, replace);
  const joined = (usePage().flash as { joined?: boolean }).joined === true;
  const form = useForm({ phone: '', name: '', email: '', birth_date: '', gender: '', marketing_consent: false, accept: false });

  return (
    <PlatformShell>
      <Head title={j('title', { company: branch.company })} />
      <div className="mx-auto flex min-h-svh max-w-md flex-col gap-4 px-4 py-6">
        <div className="flex items-center justify-between">
          {theme?.logo_url ? <img src={theme.logo_url} alt="" className="h-10 w-auto" /> : <span />}
          <LocaleSwitcher className="" />
        </div>
        <div>
          <h1 className="text-2xl font-semibold">{j('title', { company: branch.company })}</h1>
          <p className="text-sm text-muted-foreground">{j('lede', { branch: branch.name })}</p>
        </div>
        <Card>
          <CardContent>
            {joined ? (
              <div className="flex flex-col items-center gap-2 py-6 text-center">
                <BadgeCheck className="size-12 text-emerald-600" />
                <p className="text-lg font-semibold">{j('done')}</p>
                <p className="text-sm text-muted-foreground">{j('done_hint')}</p>
              </div>
            ) : (
              <form className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.post(`/join/${token}`, { preserveScroll: true }); }}>
                <FormField label={j('phone')} error={form.errors.phone}><Input dir="ltr" inputMode="tel" autoComplete="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></FormField>
                <FormField label={j('name')} error={form.errors.name}><Input autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
                <FormField label={j('email')} error={form.errors.email}><Input type="email" dir="ltr" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
                <div className="grid grid-cols-2 gap-3">
                  <FormField label={j('birth_date')} error={form.errors.birth_date}><Input type="date" dir="ltr" value={form.data.birth_date} onChange={(event) => form.setData('birth_date', event.target.value)} /></FormField>
                  <FormField label={j('gender')} error={form.errors.gender}>
                    <SelectInput value={form.data.gender} onChange={(value) => form.setData('gender', value)} emptyLabel="—" options={['male', 'female'].map((value) => ({ value, label: t(`suite.customers.genders.${value}`) }))} />
                  </FormField>
                </div>
                <label className="flex items-start gap-2 text-sm">
                  <Checkbox className="mt-0.5" checked={form.data.marketing_consent} onCheckedChange={(checked) => form.setData('marketing_consent', checked === true)} />
                  {j('consent')}
                </label>
                <label className="flex items-start gap-2 text-sm">
                  <Checkbox className="mt-0.5" checked={form.data.accept} onCheckedChange={(checked) => form.setData('accept', checked === true)} />
                  <span>{j('accept', { company: branch.company })}{form.errors.accept && <span className="block text-destructive">{form.errors.accept}</span>}</span>
                </label>
                <Button type="submit" disabled={form.processing || !form.data.accept}>{j('submit')}</Button>
              </form>
            )}
          </CardContent>
        </Card>
        {theme?.app_name && <p className="text-center text-xs text-muted-foreground">{theme.app_name}</p>}
      </div>
    </PlatformShell>
  );
}
