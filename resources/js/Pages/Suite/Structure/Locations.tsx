import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { MapPin, Pencil, Plus } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface Location {
  id: number;
  name: string;
  name_ar: string;
  name_en: string;
  latitude: number;
  longitude: number;
  radius_m: number;
  wifi_bssids: string[];
  is_active: boolean;
  branch_ids: number[];
}

function LocationDialog({ location, branches }: { location?: Location; branches: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`suite.structure.location_fields.${field}`);
  const form = useForm({
    name_ar: location?.name_ar ?? '',
    name_en: location?.name_en ?? '',
    latitude: location?.latitude ?? 24.7136,
    longitude: location?.longitude ?? 46.6753,
    radius_m: location?.radius_m ?? 100,
    wifi_bssids: (location?.wifi_bssids ?? []).join('\n'),
    is_active: location?.is_active ?? true,
    branch_ids: location?.branch_ids ?? [] as number[],
  });
  const { data, setData, errors } = form;

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((payload) => ({ ...payload, wifi_bssids: payload.wifi_bssids.split('\n').map((line) => line.trim()).filter(Boolean) }));
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    location ? form.put(url(`/structure/locations/${location.id}`), options) : form.post(url('/structure/locations'), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {location ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('suite.structure.new_location')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2">
            <DialogTitle>{location ? location.name : t('suite.structure.new_location')}</DialogTitle>
            <DialogDescription>{t('suite.structure.location_hint')}</DialogDescription>
          </DialogHeader>
          <FormField label={t('suite.structure.fields.name_ar')} error={errors.name_ar}><Input dir="rtl" value={data.name_ar} onChange={(event) => setData('name_ar', event.target.value)} /></FormField>
          <FormField label={t('suite.structure.fields.name_en')} error={errors.name_en}><Input dir="ltr" value={data.name_en} onChange={(event) => setData('name_en', event.target.value)} /></FormField>
          <FormField label={f('latitude')} error={errors.latitude}><Input type="number" step="0.0000001" dir="ltr" value={data.latitude} onChange={(event) => setData('latitude', Number(event.target.value))} /></FormField>
          <FormField label={f('longitude')} error={errors.longitude}><Input type="number" step="0.0000001" dir="ltr" value={data.longitude} onChange={(event) => setData('longitude', Number(event.target.value))} /></FormField>
          <FormField label={f('radius_m')} error={errors.radius_m}><Input type="number" min={20} max={2000} dir="ltr" value={data.radius_m} onChange={(event) => setData('radius_m', Number(event.target.value))} /></FormField>
          <FormField label={f('wifi_bssids')} error={errors.wifi_bssids}><Textarea rows={2} dir="ltr" value={data.wifi_bssids} onChange={(event) => setData('wifi_bssids', event.target.value)} /></FormField>
          <FormField label={f('branches')} error={errors.branch_ids} className="sm:col-span-2">
            <div className="flex flex-wrap gap-3">
              {branches.map((branch) => (
                <label key={branch.value} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={data.branch_ids.includes(Number(branch.value))} onCheckedChange={(state) => setData('branch_ids', state === true ? [...data.branch_ids, Number(branch.value)] : data.branch_ids.filter((id) => id !== Number(branch.value)))} />
                  {branch.label}
                </label>
              ))}
            </div>
          </FormField>
          <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.is_active} onCheckedChange={(state) => setData('is_active', state === true)} />{t('suite.structure.status_active')}</label>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function Locations({ locations, branches }: { locations: Location[]; branches: Option[] }) {
  const t = useT();
  const { can } = useAccess();
  const branchName = (id: number) => branches.find((branch) => Number(branch.value) === id)?.label ?? id;

  return (
    <SuiteLayout title={t('suite.structure.locations')}>
      <PageHeader title={t('suite.structure.locations')} description={t('suite.structure.location_hint')} actions={can('suite.structure.create') && <LocationDialog branches={branches} />} />
      <Card><CardContent>
        <ul className="divide-y">
          {locations.length === 0 && <li className="py-6 text-center text-sm text-muted-foreground">{t('common.actions.empty')}</li>}
          {locations.map((location) => (
            <li key={location.id} className="flex items-center justify-between gap-3 py-3 text-sm">
              <div className="min-w-0">
                <div className="flex items-center gap-2 font-medium"><MapPin className="size-4 text-brass" />{location.name}{!location.is_active && <Badge variant="outline" className="text-muted-foreground">—</Badge>}</div>
                <div className="mt-1 text-xs text-muted-foreground" dir="ltr">{location.latitude.toFixed(5)}, {location.longitude.toFixed(5)} · r = {location.radius_m} m</div>
                <div className="mt-1 flex flex-wrap gap-1.5">{location.branch_ids.map((id) => <Badge key={id} variant="outline">{branchName(id)}</Badge>)}</div>
              </div>
              {can('suite.structure.update') && <LocationDialog location={location} branches={branches} />}
            </li>
          ))}
        </ul>
      </CardContent></Card>
    </SuiteLayout>
  );
}
