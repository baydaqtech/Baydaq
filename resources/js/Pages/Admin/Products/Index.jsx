import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import BrandMark from '@/components/platform/BrandMark';
import DataTable from '@/components/platform/DataTable';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout';
import { useT } from '@/lib/i18n';

export default function ProductsIndex({ products }) {
  const t = useT();
  const f = (field) => t(`admin.products.fields.${field}`);

  return (
    <AdminLayout title={t('admin.products.title')}>
      <PageHeader actions={<Button asChild><Link href="/admin/products/create"><Plus data-icon="inline-start" />{t('admin.products.new')}</Link></Button>} />
      <DataTable
        rows={products}
        rowHref={(product) => `/admin/products/${product.id}/edit`}
        columns={[
          { key: 'name', label: f('name'), render: (product) => (
            <span className="flex items-center gap-2 font-medium">
              {product.logo_url ? <img src={product.logo_url} alt="" className="size-7 rounded border object-contain" /> : <BrandMark className="size-6 text-walnut" />}
              {product.name}
            </span>
          ) },
          { key: 'description', label: f('description'), className: 'max-w-sm', render: (product) => (
            <span className="line-clamp-1 text-muted-foreground">{product.description_ar || product.description_en || '—'}</span>
          ) },
          { key: 'is_active', label: f('is_active'), render: (product) => (
            <Badge variant="outline" className={product.is_active ? 'border-transparent bg-emerald-100 text-emerald-800' : 'border-transparent bg-muted text-muted-foreground'}>
              {product.is_active ? t('admin.services.active') : t('admin.services.hidden')}
            </Badge>
          ) },
        ]}
      />
    </AdminLayout>
  );
}
