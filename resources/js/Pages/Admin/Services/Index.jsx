import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { serviceIcon } from '@/lib/serviceIcons';

export default function ServicesIndex({ services }) {
  const t = useT();
  const { locale } = useLocale();
  const f = (field) => t(`admin.services.fields.${field}`);

  return (
    <AdminLayout title={t('admin.services.title')}>
      <PageHeader actions={<Button asChild><Link href="/admin/services/create"><Plus data-icon="inline-start" />{t('admin.services.new')}</Link></Button>} />
      <DataTable
        rows={services}
        rowHref={(service) => `/admin/services/${service.id}/edit`}
        columns={[
          { key: 'name', label: f('name'), render: (service) => {
            const Icon = serviceIcon(service.icon);

            return (
              <span className="flex items-center gap-2 font-medium">
                <Icon className="size-4 text-brass" />{service.name}
                <span className="font-mono text-xs font-normal text-muted-foreground" dir="ltr">{service.key}</span>
              </span>
            );
          } },
          { key: 'pricing_model', label: f('pricing_model'), render: (service) => t(`enums.pricing_model.${service.pricing_model}`) },
          { key: 'default_price', label: f('default_price'), render: (service) => (service.default_price ? formatMoney(service.default_price, locale) : '—') },
          { key: 'projects', label: t('admin.nav.projects'), render: (service) => formatNumber(service.projects_count ?? 0, locale) },
          { key: 'is_active', label: f('is_active'), render: (service) => (
            <Badge variant="outline" className={service.is_active ? 'border-transparent bg-emerald-100 text-emerald-800' : 'border-transparent bg-muted text-muted-foreground'}>
              {service.is_active ? t('admin.services.active') : t('admin.services.hidden')}
            </Badge>
          ) },
        ]}
      />
    </AdminLayout>
  );
}
