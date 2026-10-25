import { Head } from '@inertiajs/react';
import { Snowflake } from 'lucide-react';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Card, CardContent } from '@/components/ui/card';
import PlatformShell from '@/layouts/PlatformShell';
import { useT } from '@/lib/i18n';

interface Props {
  company: string | null;
  member: { name: string; number: string; code: string; qr: string };
  membership: { plan: string; starts_on: string; ends_on: string; sessions_left: number | null; is_frozen: boolean; has_started: boolean } | null;
}

/** The member's card on their phone: a QR code the door scans (S3), with the member number as a fallback. */
export default function MemberCard({ company, member, membership }: Props) {
  const t = useT();

  return (
    <PlatformShell>
      <Head title={`${t('subscriptions.card.title')} · ${company ?? ''}`} />
      <div className="mx-auto flex min-h-svh max-w-sm flex-col gap-4 px-4 py-6">
        <div className="flex justify-end"><LocaleSwitcher className="" /></div>
        <Card className="overflow-hidden">
          <div className="bg-walnut px-5 py-4 text-white">
            <p className="text-sm opacity-80">{company}</p>
            <p className="text-xl font-semibold">{member.name}</p>
          </div>
          <CardContent className="flex flex-col items-center gap-3 py-6 text-center">
            <p className="text-sm text-muted-foreground">{t('subscriptions.card.show_at_door')}</p>
            <img src={member.qr} alt={t('subscriptions.card.qr_alt')} className="size-56 rounded-lg bg-white p-2" />
            <p dir="ltr" className="font-mono text-3xl font-bold tracking-widest">{member.number}</p>
            {membership ? (
              <div className="mt-2 flex flex-col gap-1">
                <p className="font-medium">{membership.plan}</p>
                <p className="text-sm">{membership.has_started ? t('subscriptions.card.valid_until', { date: membership.ends_on }) : t('subscriptions.card.starts', { date: membership.starts_on })}</p>
                {membership.sessions_left !== null && <p className="text-sm">{t('subscriptions.card.visits_left', { count: membership.sessions_left })}</p>}
                {membership.is_frozen && <p className="flex items-center justify-center gap-1 text-sm text-sky-700"><Snowflake className="size-4" />{t('subscriptions.card.frozen')}</p>}
              </div>
            ) : <p className="text-sm text-destructive">{t('subscriptions.card.none')}</p>}
          </CardContent>
        </Card>
      </div>
    </PlatformShell>
  );
}
