import { usePage } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

export default function Dashboard() {
  const t = useT();
  const { user } = usePage().props.auth;

  return (
    <AdminLayout title={t('admin.dashboard.title')}>
      <p className="text-muted-foreground">{t('admin.dashboard.welcome', { name: user.name })}</p>
    </AdminLayout>
  );
}
