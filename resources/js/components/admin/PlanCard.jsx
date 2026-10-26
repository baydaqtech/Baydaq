import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Package, Plus, X } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

/** Override switch: inherit from the package (empty), force on, or force off. */
function Tri({ value, onChange }) {
  const t = useT();

  return (
    <SelectInput className="w-28" value={value === undefined ? '' : value ? '1' : '0'} onChange={(next) => onChange(next === '' ? undefined : next === '1')}
      options={[{ value: '1', label: t('admin.plans.on') }, { value: '0', label: t('admin.plans.off') }]} emptyLabel={t('admin.plans.as_package')} />
  );
}

function PlanDialog({ client, plan }) {
  const t = useT();
  const { dir } = useLocale();
  const [open, setOpen] = useState(false);
  const current = plan.current;
  const form = useForm({
    package_id: String(current?.package_id ?? plan.packages[0]?.value ?? ''),
    status: current?.status ?? 'active', billing_cycle: current?.billing_cycle ?? 'monthly',
    starts_on: current?.starts_on ?? new Date().toISOString().slice(0, 10), ends_on: current?.ends_on ?? '',
    addons: current?.addons ?? [],
    overrides: { limits: current?.overrides?.limits ?? {}, features: current?.overrides?.features ?? {}, mobile: current?.overrides?.mobile ?? {} },
  });
  const { data, setData, errors } = form;
  const setOverride = (group, key, value) => {
    const next = { ...data.overrides[group] };
    if (value === undefined || value === '') {
      delete next[key];
    } else {
      next[key] = value;
    }
    setData('overrides', { ...data.overrides, [group]: next });
  };

  function submit(event) {
    event.preventDefault();
    form.transform((values) => ({ ...values, ends_on: values.ends_on || null }));
    form.post(`/admin/clients/${client.id}/plan`, { preserveScroll: true, onSuccess: () => setOpen(false) });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">{current ? t('admin.plans.change') : t('admin.plans.assign')}</Button></DialogTrigger>
      <DialogContent dir={dir} className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('admin.plans.title')}</DialogTitle><DialogDescription>{t('admin.plans.hint')}</DialogDescription></DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField label={t('admin.plans.package')} error={errors.package_id}><SelectInput value={data.package_id} onChange={(value) => setData('package_id', value)} options={plan.packages} /></FormField>
            <FormField label={t('admin.plans.status')} error={errors.status}><SelectInput value={data.status} onChange={(value) => setData('status', value)} options={['trial', 'active', 'suspended'].map((value) => ({ value, label: t(`admin.plans.statuses.${value}`) }))} /></FormField>
            <FormField label={t('admin.plans.starts_on')} error={errors.starts_on}><Input type="date" dir="ltr" value={data.starts_on} onChange={(event) => setData('starts_on', event.target.value)} /></FormField>
            <FormField label={t('admin.plans.ends_on')} error={errors.ends_on}><Input type="date" dir="ltr" value={data.ends_on} onChange={(event) => setData('ends_on', event.target.value)} /></FormField>
            <FormField label={t('admin.plans.billing_cycle')}><SelectInput value={data.billing_cycle} onChange={(value) => setData('billing_cycle', value)} options={['monthly', 'yearly'].map((value) => ({ value, label: t(`admin.plans.cycles.${value}`) }))} /></FormField>
          </div>
          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium">{t('admin.plans.addons')}</span>
            {data.addons.map((line, index) => (
              <div key={index} className="flex gap-2">
                <SelectInput className="flex-1" value={String(line.addon_id)} onChange={(value) => setData('addons', data.addons.map((item, i) => (i === index ? { ...item, addon_id: Number(value) } : item)))} options={plan.addons} />
                <Input className="w-24" type="number" min="1" dir="ltr" value={line.quantity ?? 1} onChange={(event) => setData('addons', data.addons.map((item, i) => (i === index ? { ...item, quantity: Number(event.target.value) } : item)))} />
                <Button type="button" size="icon-sm" variant="ghost" onClick={() => setData('addons', data.addons.filter((_, i) => i !== index))}><X /></Button>
              </div>
            ))}
            {plan.addons.length > 0 && <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => setData('addons', [...data.addons, { addon_id: plan.addons[0].value, quantity: 1 }])}><Plus data-icon="inline-start" />{t('admin.plans.add_addon')}</Button>}
          </div>
          <details className="rounded-md border p-3">
            <summary className="cursor-pointer text-sm font-medium">{t('admin.plans.overrides')}</summary>
            <div className="mt-3 flex flex-col gap-4">
              <div className="grid gap-2 sm:grid-cols-3">
                {plan.catalog.limits.map((limit) => (
                  <FormField key={limit.value} label={limit.label}>
                    <Input type="number" min="0" dir="ltr" placeholder={t('admin.plans.as_package')} value={data.overrides.limits[limit.value] ?? ''} onChange={(event) => setOverride('limits', limit.value, event.target.value === '' ? undefined : Number(event.target.value))} />
                  </FormField>
                ))}
              </div>
              {[...Object.values(plan.catalog.features).flat().map((option) => ['features', option]), ...plan.catalog.mobile.map((option) => ['mobile', option])].map(([group, option]) => (
                <div key={option.value} className="flex items-center justify-between gap-2 text-sm">
                  <span>{option.label}</span>
                  <Tri value={data.overrides[group][option.value]} onChange={(value) => setOverride(group, option.value, value)} />
                </div>
              ))}
            </div>
          </details>
          <DialogFooter><Button type="submit" disabled={form.processing || !data.package_id}>{t('admin.common.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/** The client's package: what it includes, limits against usage, and changing it. */
export default function PlanCard({ client, plan, canManage }) {
  const t = useT();
  const { locale } = useLocale();
  const { current, effective, usage } = plan;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="flex items-center gap-2"><Package className="size-4 text-brass" />{t('admin.plans.title')}</CardTitle>
        {canManage && plan.packages.length > 0 && <PlanDialog client={client} plan={plan} />}
      </CardHeader>
      <CardContent className="flex flex-col gap-3 text-sm">
        {!current && <p className="text-muted-foreground">{plan.packages.length ? t('admin.plans.none') : t('admin.plans.no_packages')}</p>}
        {current && (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <span className="font-medium">{current.package}</span>
              <Badge variant={current.status === 'suspended' ? 'destructive' : 'outline'}>{t(`admin.plans.statuses.${current.status}`)}</Badge>
              <span className="text-muted-foreground">{formatDate(current.starts_on, locale)}{current.ends_on && ` → ${formatDate(current.ends_on, locale)}`}</span>
            </div>
            <div className="grid gap-2 sm:grid-cols-3">
              {plan.catalog.limits.filter((limit) => limit.value in usage).map((limit) => {
                const cap = effective.limits[limit.value];
                const used = usage[limit.value];

                return (
                  <div key={limit.value} className="rounded-md border p-2">
                    <div className="text-xs text-muted-foreground">{limit.label}</div>
                    <div className={used >= (cap ?? Infinity) ? 'font-semibold text-red-700' : 'font-semibold'}>{used} / {cap ?? '∞'}</div>
                  </div>
                );
              })}
            </div>
            <p className="text-xs text-muted-foreground">{t('admin.plans.summary', { modules: effective.modules.length, features: effective.features.length, mobile: effective.mobile.length })}</p>
          </>
        )}
      </CardContent>
    </Card>
  );
}
