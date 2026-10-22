import { usePage } from '@inertiajs/react';
import PortalLayout from '@/layouts/PortalLayout';
import { useT } from '@/lib/i18n';

export default function Home() {
  const t = useT();
  const { user } = usePage().props.auth;

  return (
    <PortalLayout title={t('portal.home.title')}>
      <h1 className="text-xl font-semibold">{t('portal.home.title')}</h1>
      <p className="mt-1 text-muted-foreground">{t('portal.home.welcome', { name: user.name })}</p>
    </PortalLayout>
  );
}
