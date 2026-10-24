import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import type { Paginated } from '@/types/resources';

interface Punch {
  id: number;
  employee_id: number;
  employee: string;
  employee_number: string;
  type: 'in' | 'out';
  punched_at: string;
  branch: string;
  home_branch: string;
  accuracy_m: number | null;
  flag: string | null;
  resolution: string | null;
  resolution_note: string | null;
  can_resolve: boolean;
  [key: string]: unknown;
}

function ResolveDialog({ punch }: { punch: Punch }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ resolution: 'accepted', note: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="xs" variant="outline">{t('hr.attendance.resolve')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/attendance/punches/${punch.id}/resolve`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{punch.employee} · {t(`hr.punch_flag.${punch.flag}`)}</DialogTitle></DialogHeader>
          <FormField label={t('hr.attendance.fields.resolution')} error={form.errors.resolution}>
            <SelectInput value={form.data.resolution} onChange={(value) => form.setData('resolution', value)} options={[{ value: 'accepted', label: t('hr.attendance.accept') }, { value: 'rejected', label: t('hr.attendance.reject') }]} />
          </FormField>
          <FormField label={t('hr.attendance.fields.note')} error={form.errors.note}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function AttendanceIndex({ punches, filters: initialFilters }: { punches: Paginated<Punch>; filters: Filters }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [filters, setFilter] = useFilters(initialFilters);
  const f = (field: string) => t(`hr.attendance.fields.${field}`);
  const time = (iso: string) => new Date(iso).toLocaleTimeString(locale === 'ar' ? 'ar-SA' : 'en-GB', { hour: '2-digit', minute: '2-digit' });

  return (
    <SuiteLayout title={t('hr.attendance.title')}>
      <PageHeader title={t('hr.attendance.title')} />
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Input type="date" dir="ltr" className="w-44" value={String(filters.date ?? '')} onChange={(event) => setFilter('date', event.target.value)} />
        <label className="flex items-center gap-2 text-sm"><Checkbox checked={Boolean(filters.flagged)} onCheckedChange={(state) => setFilter('flagged', state === true ? 1 : null)} />{t('hr.attendance.flagged')}</label>
      </div>
      <DataTable<Punch>
        rows={punches}
        empty={t('hr.attendance.none')}
        columns={[
          { key: 'employee', label: f('employee'), render: (punch) => <Link href={url(`/hr/employees/${punch.employee_id}`)} className="font-medium hover:underline">{punch.employee}<span className="block font-mono text-xs text-muted-foreground" dir="ltr">{punch.employee_number}</span></Link> },
          { key: 'type', label: f('type'), render: (punch) => <StatusBadge group="punch_type" value={punch.type} file="hr" /> },
          { key: 'time', label: f('time'), render: (punch) => <span dir="ltr">{time(punch.punched_at)}</span> },
          { key: 'branch', label: f('branch'), render: (punch) => <span>{punch.branch}{punch.branch !== punch.home_branch && <span className="block text-xs text-muted-foreground">{f('home_branch')}: {punch.home_branch}</span>}</span> },
          { key: 'flag', label: f('flag'), render: (punch) => punch.flag ? (
            <span className="flex flex-wrap items-center gap-1.5">
              <Flag className="size-3.5 text-amber-600" />{t(`hr.punch_flag.${punch.flag}`)}
              {punch.resolution && <StatusBadge group="punch_resolution" value={punch.resolution} file="hr" />}
              {punch.resolution_note && <span className="text-xs text-muted-foreground">· {punch.resolution_note}</span>}
            </span>
          ) : <span className="text-muted-foreground">—</span> },
          { key: 'actions', label: '', className: 'text-end', render: (punch) => punch.can_resolve && <ResolveDialog punch={punch} /> },
        ]}
      />
    </SuiteLayout>
  );
}
