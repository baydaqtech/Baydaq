import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, Plane } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface Visa {
  id: number; employee_id: number; kind: string; number: string | null; status: string; issued_on: string; expires_on: string; duration_days: number | null;
  departed_on: string | null; returned_on: string | null; return_by: string | null; alert: 'unused' | 'overdue' | null; employee?: string | null;
}

interface Props {
  employees: { id: number; name: string; number: string; nationality: string | null; visas: Visa[] }[];
  alerts: Visa[];
  kinds: string[];
  can_manage: boolean;
  portal: string;
}

const NEXT: Record<string, string[]> = { issued: ['depart', 'cancel'], abroad: ['return', 'not_returned'] };

function AddVisa({ employeeId, kinds }: { employeeId: number; kinds: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const m = (key: string) => t(`hr.gov.muqeem.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ kind: 'single', number: '', issued_on: new Date().toISOString().slice(0, 10), expires_on: '', duration_days: '60', notes: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><Plane />{m('add')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{m('add')}</DialogTitle></DialogHeader>
        <form id={`visa-${employeeId}`} className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employeeId}/visas`), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}>
          <FormField label={m('kind')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds.map((value) => ({ value, label: m(`kinds.${value}`) }))} /></FormField>
          <FormField label={m('number')} error={form.errors.number}><Input dir="ltr" value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} /></FormField>
          <FormField label={m('issued_on')} error={form.errors.issued_on}><Input type="date" dir="ltr" value={form.data.issued_on} onChange={(event) => form.setData('issued_on', event.target.value)} /></FormField>
          <FormField label={m('expires_on')} error={form.errors.expires_on}><Input type="date" dir="ltr" value={form.data.expires_on} onChange={(event) => form.setData('expires_on', event.target.value)} /></FormField>
          {form.data.kind !== 'final_exit' && <FormField label={m('duration_days')} error={form.errors.duration_days}><Input type="number" min="1" dir="ltr" value={form.data.duration_days} onChange={(event) => form.setData('duration_days', event.target.value)} /></FormField>}
          <FormField label={m('notes')} error={form.errors.notes} className="sm:col-span-2"><Input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form={`visa-${employeeId}`} disabled={form.processing}>{m('save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function VisaLine({ visa, canManage }: { visa: Visa; canManage: boolean }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const m = (key: string) => t(`hr.gov.muqeem.${key}`);
  const actions = (NEXT[visa.status] ?? []).filter((action) => !(action === 'return' && visa.kind === 'final_exit'));

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium">{m(`kinds.${visa.kind}`)}</span>
          <Badge variant={visa.alert ? 'destructive' : 'outline'}>{m(`statuses.${visa.status}`)}</Badge>
          {visa.number && <span className="text-xs text-muted-foreground" dir="ltr">{visa.number}</span>}
        </div>
        <div className="text-xs text-muted-foreground">
          {visa.status === 'issued' && `${m('expires_on')}: ${formatDate(visa.expires_on, locale)}`}
          {visa.departed_on && `${m('departed_on')}: ${formatDate(visa.departed_on, locale)}`}
          {visa.return_by && visa.status === 'abroad' && ` · ${m('return_by')}: ${formatDate(visa.return_by, locale)}`}
          {visa.returned_on && ` · ${m('returned_on')}: ${formatDate(visa.returned_on, locale)}`}
        </div>
        {visa.alert && <div className="text-xs text-destructive">{m(`alert.${visa.alert}`)}</div>}
      </div>
      {canManage && actions.length > 0 && (
        <div className="flex gap-1">
          {actions.map((action) => <Button key={action} size="sm" variant="ghost" onClick={() => router.put(url(`/hr/gov/visas/${visa.id}`), { action }, { preserveScroll: true })}>{m(`actions.${action}`)}</Button>)}
        </div>
      )}
    </div>
  );
}

/** Exit/re-entry and final exit visas from Muqeem, followed from issue to return. */
export default function Muqeem({ employees, alerts, kinds, can_manage: canManage, portal }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const m = (key: string) => t(`hr.gov.muqeem.${key}`);

  return (
    <SuiteLayout title={m('title')}>
      <PageHeader title={m('title')} description={m('lede')} actions={<Button asChild variant="outline"><a href={portal} target="_blank" rel="noreferrer"><ExternalLink />{m('open_portal')}</a></Button>} />
      <Card className="mb-4">
        <CardHeader><CardTitle className="flex items-center gap-2"><AlertTriangle className="size-4" />{m('alerts')}</CardTitle></CardHeader>
        <CardContent className="divide-y">
          {alerts.length === 0 && <p className="text-sm text-muted-foreground">{m('no_alerts')}</p>}
          {alerts.map((visa) => <div key={visa.id}><div className="pt-2 text-sm font-medium">{visa.employee}</div><VisaLine visa={visa} canManage={canManage} /></div>)}
        </CardContent>
      </Card>
      {employees.length === 0 && <p className="text-sm text-muted-foreground">{m('none')}</p>}
      <div className="grid gap-3 lg:grid-cols-2">
        {employees.map((employee) => (
          <Card key={employee.id}>
            <CardHeader>
              <CardTitle className="flex items-center justify-between gap-2">
                <Link href={url(`/hr/employees/${employee.id}`)} className="hover:underline">{employee.name} <span className="text-xs font-normal text-muted-foreground" dir="ltr">{employee.number} · {employee.nationality}</span></Link>
                {canManage && <AddVisa employeeId={employee.id} kinds={kinds} />}
              </CardTitle>
            </CardHeader>
            <CardContent className="divide-y">
              {employee.visas.length === 0 && <p className="text-sm text-muted-foreground">{m('no_visas')}</p>}
              {employee.visas.map((visa) => <VisaLine key={visa.id} visa={visa} canManage={canManage} />)}
            </CardContent>
          </Card>
        ))}
      </div>
    </SuiteLayout>
  );
}
