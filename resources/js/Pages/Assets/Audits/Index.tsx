import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import DataTable from '@/components/platform/DataTable';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

interface AuditRow { id: number; branch: string | null; started_on: string; closed_at: string | null; items: number; found: number; missing: number; unexpected: number; [key: string]: unknown }

function StartAudit({ branches }: { branches: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ branch_id: branches[0]?.value ?? '', note: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><ScanLine data-icon="inline-start" />{t('assets.audits.new')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url('/assets/audits')); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('assets.audits.new')}</DialogTitle></DialogHeader>
          <FormField label={t('assets.audits.fields.branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', Number(value))} options={branches} /></FormField>
          <FormField label={t('assets.audits.fields.note')}><Input value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></FormField>
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('assets.audits.new')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function AuditsIndex({ audits, branches }: { audits: AuditRow[]; branches: Option[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const f = (field: string) => t(`assets.audits.fields.${field}`);

  return (
    <SuiteLayout title={t('assets.audits.title')}>
      <PageHeader title={t('assets.audits.title')} actions={can('assets.audits.create') && branches.length > 0 && <StartAudit branches={branches} />} />
      <DataTable<AuditRow>
        rows={audits}
        rowHref={(audit) => url(`/assets/audits/${audit.id}`)}
        empty={t('assets.audits.none')}
        columns={[
          { key: 'branch', label: f('branch'), render: (audit) => <span className="font-medium">{audit.branch}</span> },
          { key: 'started_on', label: f('started_on'), render: (audit) => <span dir="ltr" className="tabular-nums">{audit.started_on}</span> },
          { key: 'closed_at', label: f('closed_at'), render: (audit) => (audit.closed_at ? <span dir="ltr" className="tabular-nums">{audit.closed_at}</span> : <Badge variant="outline" className="border-transparent bg-sky-100 text-sky-800">{t('assets.audits.open')}</Badge>) },
          { key: 'items', label: f('items'), render: (audit) => audit.items },
          { key: 'found', label: f('found'), render: (audit) => <span className="text-emerald-700">{audit.found}</span> },
          { key: 'missing', label: f('missing'), render: (audit) => <span className={audit.missing ? 'text-destructive' : undefined}>{audit.missing}</span> },
          { key: 'unexpected', label: f('unexpected'), render: (audit) => audit.unexpected },
        ]}
      />
    </SuiteLayout>
  );
}
