import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

/** Packages and add-ons Baydaq sells. */
export default function PackagesIndex({ packages, addons }) {
  const t = useT();
  const { locale } = useLocale();
  const p = (field) => t(`admin.packages.${field}`);
  const limits = (row) => Object.entries(row.limits ?? {}).filter(([, value]) => value !== null).map(([key, value]) => `${t(`suite.catalog.limit_${key}`)}: ${value}`).join(' · ') || p('unlimited');
  const columns = (kind) => [
    { key: 'name', label: p('name'), render: (row) => <span className="font-medium">{row.name} <span dir="ltr" className="font-mono text-xs text-muted-foreground">{row.code}</span></span> },
    { key: 'price', label: p('price_monthly'), render: (row) => formatMoney(row.price_monthly, locale) },
    { key: 'modules', label: p('modules'), render: (row) => (row.modules ?? []).map((module) => t(`suite.module.${module}`)).join('، ') },
    { key: 'counts', label: p('features'), render: (row) => `${row.features} · ${p('mobile_short')} ${row.mobile}` },
    { key: 'limits', label: p('limits'), render: (row) => <span className="text-xs text-muted-foreground">{limits(row)}</span> },
    { key: 'flags', label: '', render: (row) => (
      <span className="flex gap-1">
        {!row.is_active && <Badge variant="outline">{p('inactive')}</Badge>}
        {kind === 'package' && row.is_public && <Badge variant="secondary">{p('public')}</Badge>}
      </span>
    ) },
  ];

  return (
    <AdminLayout title={p('title')}>
      <PageHeader
        title={p('title')}
        description={p('lede')}
        actions={(
          <>
            <Button asChild variant="outline"><Link href="/admin/packages/create?kind=addon"><Plus data-icon="inline-start" />{p('new_addon')}</Link></Button>
            <Button asChild><Link href="/admin/packages/create"><Plus data-icon="inline-start" />{p('new_package')}</Link></Button>
          </>
        )}
      />
      <h2 className="mb-2 text-sm font-semibold">{p('packages')}</h2>
      <DataTable rows={packages} rowHref={(row) => `/admin/packages/package/${row.id}/edit`} empty={p('none')} columns={columns('package')} />
      <h2 className="mt-8 mb-2 text-sm font-semibold">{p('addons')}</h2>
      <DataTable rows={addons} rowHref={(row) => `/admin/packages/addon/${row.id}/edit`} empty={p('none')} columns={columns('addon')} />
    </AdminLayout>
  );
}
