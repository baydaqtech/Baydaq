import { Head, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import PlatformShell from '@/layouts/PlatformShell';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

interface Props {
  token: string;
  opening: { title: string; description: string | null; accepting: boolean; company: string | null; branch: string | null; closes_on: string | null };
  theme: { logo_url: string | null } | null;
}

/** An opening's public careers page: the job, and the application form with a CV. */
export default function Careers({ token, opening, theme }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const c = (key: string, replace?: Record<string, string>) => t(`hr.careers.${key}`, replace);
  const applied = (usePage().flash as { applied?: boolean }).applied === true;
  const form = useForm<{ name: string; phone: string; email: string; nationality: string; cv: File | null; consent: boolean }>({ name: '', phone: '', email: '', nationality: '', cv: null, consent: false });

  return (
    <PlatformShell>
      <Head title={`${opening.title} · ${opening.company ?? ''}`} />
      <div className="mx-auto flex min-h-svh max-w-xl flex-col gap-4 px-4 py-6">
        <div className="flex items-center justify-between">
          {theme?.logo_url ? <img src={theme.logo_url} alt="" className="h-10 w-auto" /> : <span />}
          <LocaleSwitcher className="" />
        </div>
        <div>
          <p className="text-sm text-muted-foreground">{[opening.company, opening.branch].filter(Boolean).join(' · ')}</p>
          <h1 className="text-2xl font-semibold">{opening.title}</h1>
          {opening.closes_on && <p className="text-sm text-muted-foreground">{c('closes_on', { date: formatDate(opening.closes_on, locale) })}</p>}
        </div>
        {opening.description && <p className="whitespace-pre-line text-sm">{opening.description}</p>}
        <Card>
          <CardContent>
            {applied ? (
              <div className="flex flex-col items-center gap-2 py-6 text-center">
                <BadgeCheck className="size-12 text-emerald-600" />
                <p className="text-lg font-semibold">{c('done')}</p>
                <p className="text-sm text-muted-foreground">{c('done_hint')}</p>
              </div>
            ) : !opening.accepting ? <p className="text-sm text-muted-foreground">{c('closed')}</p> : (
              <form className="flex flex-col gap-3" onSubmit={(event) => { event.preventDefault(); form.post(`/careers/${token}`, { forceFormData: true, preserveScroll: true }); }}>
                <FormField label={c('name')} error={form.errors.name}><Input autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
                <FormField label={c('phone')} error={form.errors.phone}><Input dir="ltr" inputMode="tel" autoComplete="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></FormField>
                <FormField label={c('email')} error={form.errors.email}><Input type="email" dir="ltr" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
                <FormField label={c('nationality')} error={form.errors.nationality}><Input dir="ltr" maxLength={2} value={form.data.nationality} onChange={(event) => form.setData('nationality', event.target.value.toUpperCase())} /></FormField>
                <FormField label={c('cv')} error={form.errors.cv}><Input type="file" accept=".pdf,.doc,.docx,image/*" onChange={(event) => form.setData('cv', event.target.files?.[0] ?? null)} /></FormField>
                <label className="flex items-start gap-2 text-sm">
                  <Checkbox className="mt-0.5" checked={form.data.consent} onCheckedChange={(checked) => form.setData('consent', checked === true)} />
                  <span>{c('consent')}{form.errors.consent && <span className="block text-destructive">{form.errors.consent}</span>}</span>
                </label>
                <Button type="submit" disabled={form.processing || !form.data.consent}>{c('apply')}</Button>
              </form>
            )}
          </CardContent>
        </Card>
      </div>
    </PlatformShell>
  );
}
