import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Paginated } from '@/types/resources';

interface Customer {
  id: number; name: string; name_ar: string; name_en: string; vat_number: string | null; cr_number: string | null; email: string | null; phone: string | null;
  street: string | null; building_number: string | null; district: string | null; city: string | null; postal_code: string | null; country: string; is_active: boolean;
  [key: string]: unknown;
}

const FIELDS = ['name_ar', 'name_en', 'vat_number', 'cr_number', 'email', 'phone', 'street', 'building_number', 'district', 'city', 'postal_code', 'country'] as const;

function CustomerDialog({ customer }: { customer?: Customer }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm(Object.fromEntries(FIELDS.map((field) => [field, customer?.[field] ?? (field === 'country' ? 'SA' : '')])) as Record<(typeof FIELDS)[number], string>);
  const f = (field: string) => t(`invoicing.customers.fields.${field}`);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    customer ? form.put(url(`/invoicing/customers/${customer.id}`), options) : form.post(url('/invoicing/customers'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{customer ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('invoicing.customers.new')}</Button>}</DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{customer ? customer.name : t('invoicing.customers.new')}</DialogTitle></DialogHeader>
          {FIELDS.map((field) => (
            <FormField key={field} label={f(field)} error={form.errors[field]}>
              <Input dir={field === 'name_ar' || field === 'street' || field === 'district' || field === 'city' ? undefined : 'ltr'} value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} />
            </FormField>
          ))}
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function CustomersIndex({ customers, filters: initialFilters }: { customers: Paginated<Customer>; filters: Filters }) {
  const t = useT();
  const { can } = useAccess();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`invoicing.customers.fields.${field}`);

  return (
    <SuiteLayout title={t('invoicing.customers.title')}>
      <PageHeader title={t('invoicing.customers.title')} actions={can('invoicing.customers.create') && <CustomerDialog />} />
      <FilterBar filters={filters} setFilter={setFilter} />
      <DataTable<Customer>
        rows={customers}
        empty={t('invoicing.customers.none')}
        columns={[
          { key: 'name', label: f('name_ar'), render: (customer) => <span><span className="block font-medium">{customer.name_ar}</span><bdi className="text-xs text-muted-foreground">{customer.name_en}</bdi></span> },
          { key: 'vat_number', label: f('vat_number'), render: (customer) => <span dir="ltr">{customer.vat_number ?? '—'}</span> },
          { key: 'city', label: f('city'), render: (customer) => customer.city ?? '—' },
          { key: 'phone', label: f('phone'), render: (customer) => <span dir="ltr">{customer.phone ?? '—'}</span> },
          { key: 'actions', label: '', className: 'text-end', render: (customer) => can('invoicing.customers.update') && <CustomerDialog customer={customer} /> },
        ]}
      />
    </SuiteLayout>
  );
}
