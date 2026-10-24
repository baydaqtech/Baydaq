import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Warehouse as WarehouseIcon } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusMessage from '@/components/platform/StatusMessage';
import SwitchField from '@/components/platform/SwitchField';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Link } from '@inertiajs/react';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { ScopedOption } from '@/types/pos';
import type { Option } from '@/types/resources';

interface WarehouseRow { id: number; code: string; name: string; name_ar: string; name_en: string; branch_id: number; branch: string | null; kind: string; keeper_employee_id: number | null; keeper: string | null; is_active: boolean; lines: number; value: string; low: number }
interface Holding { employee_id: number; employee: string; lines: { label: string; quantity: string; unit: string }[] }
interface Person extends Option { company_id: number; branch_id: number }

interface Props { warehouses: WarehouseRow[]; holdings: Holding[]; branches: ScopedOption[]; people: Person[]; kinds: Option[] }

function WarehouseDialog({ warehouse, branches, people, kinds }: { warehouse?: WarehouseRow } & Omit<Props, 'warehouses' | 'holdings'>) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`assets.inventory.fields.${field}`);
  const form = useForm({ branch_id: warehouse?.branch_id ?? branches[0]?.value ?? '', code: warehouse?.code ?? '', name_ar: warehouse?.name_ar ?? '', name_en: warehouse?.name_en ?? '', kind: warehouse?.kind ?? 'store', keeper_employee_id: warehouse?.keeper_employee_id ?? ('' as number | ''), is_active: warehouse?.is_active ?? true });
  const companyId = branches.find((branch) => Number(branch.value) === Number(form.data.branch_id))?.company_id;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, keeper_employee_id: data.keeper_employee_id || null }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    warehouse ? form.put(url(`/assets/inventory/warehouses/${warehouse.id}`), options) : form.post(url('/assets/inventory/warehouses'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{warehouse ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('assets.inventory.new_warehouse')}</Button>}</DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{warehouse ? warehouse.name : t('assets.inventory.new_warehouse')}</DialogTitle></DialogHeader>
          {!warehouse && <FormField label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', Number(value))} options={branches} /></FormField>}
          {!warehouse && <FormField label={f('code')} error={form.errors.code}><Input dir="ltr" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} /></FormField>}
          <FormField label={f('name_ar')} error={form.errors.name_ar}><Input value={form.data.name_ar} onChange={(event) => form.setData('name_ar', event.target.value)} /></FormField>
          <FormField label={f('name_en')} error={form.errors.name_en}><Input dir="ltr" value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('kind')}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds} /></FormField>
          <FormField label={f('keeper')} error={form.errors.keeper_employee_id}><SelectInput value={form.data.keeper_employee_id} onChange={(value) => form.setData('keeper_employee_id', value === '' ? '' : Number(value))} options={people.filter((person) => person.company_id === companyId)} emptyLabel="—" /></FormField>
          <SwitchField id="warehouse-active" label={f('is_active')} checked={form.data.is_active} onChange={(value: boolean) => form.setData('is_active', value)} />
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Warehouses(props: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const { warehouses, holdings } = props;

  return (
    <SuiteLayout title={t('assets.inventory.warehouses')}>
      <PageHeader title={t('assets.inventory.warehouses')} actions={can('assets.inventory.create') && props.branches.length > 0 && <WarehouseDialog {...props} />} />
      {warehouses.length === 0 ? <StatusMessage>{t('assets.inventory.none')}</StatusMessage> : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {warehouses.map((warehouse) => (
            <Card key={warehouse.id} className={warehouse.is_active ? undefined : 'opacity-60'}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2 text-base">
                  <Link href={url(`/assets/inventory/warehouses/${warehouse.id}`)} className="flex items-center gap-2 hover:underline"><WarehouseIcon className="size-4 text-muted-foreground" />{warehouse.name} <span dir="ltr" className="font-mono text-xs text-muted-foreground">{warehouse.code}</span></Link>
                  {can('assets.inventory.update') && <WarehouseDialog warehouse={warehouse} {...props} />}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-1 text-sm">
                <span className="text-muted-foreground">{t(`assets.warehouse_kind.${warehouse.kind}`)} · {warehouse.branch}{warehouse.keeper ? ` · ${warehouse.keeper}` : ''}</span>
                <span className="flex flex-wrap items-center gap-2">
                  <span className="tabular-nums">{formatMoney(warehouse.value, locale)}</span>
                  <span className="text-xs text-muted-foreground">({formatNumber(warehouse.lines, locale)})</span>
                  {warehouse.low > 0 && <Badge variant="outline" className="border-transparent bg-amber-100 text-amber-800">{t('assets.inventory.low', { count: warehouse.low })}</Badge>}
                </span>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <h2 className="mt-6 mb-2 text-sm font-semibold text-muted-foreground">{t('assets.inventory.holdings')}</h2>
      {holdings.length === 0 ? <StatusMessage>{t('assets.inventory.no_holdings')}</StatusMessage> : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {holdings.map((holding) => (
            <Card key={holding.employee_id}>
              <CardContent className="py-4 text-sm">
                <p className="mb-1 font-medium">{holding.employee}</p>
                <ul className="flex flex-col gap-0.5 text-muted-foreground">{holding.lines.map((line) => <li key={line.label} className="flex justify-between gap-2"><span>{line.label}</span><span className="tabular-nums">{formatNumber(line.quantity, locale)} {line.unit}</span></li>)}</ul>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </SuiteLayout>
  );
}
