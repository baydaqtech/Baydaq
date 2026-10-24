import { AlertTriangle, CheckCircle2, Wallet } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import InvoiceTable from '@/components/platform/InvoiceTable';
import StatCard from '@/components/platform/StatCard';
import StatusBadge from '@/components/platform/StatusBadge';
import PortalLayout from '@/layouts/PortalLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export default function Statement({ invoices, totals, subscriptions }) {
  const t = useT();
  const { locale } = useLocale();
  const st = (field) => t(`portal.statement.${field}`);

  return (
    <PortalLayout title={st('title')}>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold">{st('title')}</h1>
        <p className="mt-1 text-muted-foreground">{st('lede')}</p>
      </div>

      <div className="mb-8 grid gap-3 sm:grid-cols-3">
        <StatCard icon={Wallet} label={t('portal.billing.due')} value={formatMoney(totals.due, locale)} />
        <StatCard icon={AlertTriangle} tone="bg-red-100 text-red-700" label={t('portal.billing.overdue')} value={formatMoney(totals.overdue, locale)} />
        <StatCard icon={CheckCircle2} tone="bg-emerald-100 text-emerald-700" label={t('portal.billing.paid_this_year')} value={formatMoney(totals.paid_this_year, locale)} />
      </div>

      <section className="mb-8">
        <h2 className="mb-3 text-lg font-semibold">{st('subscriptions')}</h2>
        <DataTable
          rows={subscriptions}
          empty={st('no_subscriptions')}
          columns={[
            { key: 'module', label: st('module'), render: (row) => <span className="font-medium">{row.module}</span> },
            { key: 'quantity', label: st('quantity'), render: (row) => `${formatNumber(row.quantity, locale)} ${row.unit}` },
            { key: 'period', label: st('period'), render: (row) => (
              <span>{formatDate(row.starts_on, locale)}{row.ends_on && ` → ${formatDate(row.ends_on, locale)}`}
                {row.days_left !== null && row.status === 'active' && <span className="block text-xs text-muted-foreground">{row.days_left > 0 ? st('renews').replace(':days', formatNumber(row.days_left, locale)) : st('ended')}</span>}
              </span>
            ) },
            { key: 'total', label: st('yearly'), render: (row) => formatMoney(row.total, locale) },
            { key: 'status', label: t('portal.billing.status'), render: (row) => <StatusBadge group="subscription_status" value={row.status} file="suite" /> },
          ]}
        />
      </section>

      <section>
        <h2 className="mb-3 text-lg font-semibold">{st('invoices')}</h2>
        <InvoiceTable invoices={invoices} showProject />
        <p className="mt-3 text-sm text-muted-foreground">{t('portal.billing.how_to_pay')}</p>
      </section>
    </PortalLayout>
  );
}
