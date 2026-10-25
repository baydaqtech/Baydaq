import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import PageHeader from '@/components/platform/PageHeader';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Paginated } from '@/types/resources';

interface CustomerRow { id: number; name: string; phone: string; email: string | null; wallet_balance: string; [key: string]: unknown }

export default function CustomersIndex({ customers, filters: initialFilters }: { customers: Paginated<CustomerRow>; filters: Filters }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const c = (field: string) => t(`suite.customers.${field}`);

  return (
    <SuiteLayout title={c('title')}>
      <PageHeader title={c('title')} description={c('lede')} />
      <FilterBar filters={filters} setFilter={setFilter} />
      <DataTable<CustomerRow>
        rows={customers}
        rowHref={(customer) => url(`/customers/${customer.id}`)}
        empty={c('none')}
        columns={[
          { key: 'name', label: c('name'), render: (customer) => <span className="font-medium">{customer.name}</span> },
          { key: 'phone', label: c('phone'), render: (customer) => <span dir="ltr">+{customer.phone}</span> },
          { key: 'email', label: c('email'), render: (customer) => <span dir="ltr" className="text-muted-foreground">{customer.email}</span> },
          { key: 'wallet_balance', label: c('balance'), render: (customer) => formatMoney(customer.wallet_balance, locale) },
        ]}
      />
    </SuiteLayout>
  );
}
