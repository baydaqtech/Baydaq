import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeftRight, FileText, KeyRound, Paperclip, Pencil, Plus, QrCode, Smartphone, Sparkles, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import DetailList from '@/components/platform/DetailList';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import StatusBadge from '@/components/platform/StatusBadge';
import DynamicFields, { type FieldValues } from '@/components/hr/DynamicFields';
import LeaveTab, { type LeaveData } from '@/components/hr/LeaveTab';
import PayTab, { type PayData } from '@/components/hr/PayTab';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { postJson } from '@/lib/http';
import { useSuite, useSuiteUrl } from '@/lib/suite';
import type { AuditEntry, BranchOption, CatalogItem, ContractAllowanceLine, ContractResource, EmployeeDocumentResource, EmployeeResource, MovementResource } from '@/types/hr';
import type { Option } from '@/types/resources';

interface Props {
  employee: EmployeeResource;
  company: string | null;
  branch: string | null;
  login: { id: number; name: string; email: string; pending: boolean } | null;
  suggested_role: string | null;
  end_of_service: EndOfServiceData | null;
  pending_changes: PendingChange[];
  device: { model: string | null; platform: string | null; bound_at: string } | null;
  contracts: ContractResource[];
  documents: EmployeeDocumentResource[];
  movements: MovementResource[];
  audit: AuditEntry[];
  statuses: Option[];
  branches: BranchOption[];
  allowance_types: CatalogItem[];
  document_types: CatalogItem[];
  pay: PayData | null;
  leave: LeaveData | null;
  letters: LetterRow[];
  can_salary_letter: boolean;
}

interface LetterRow { id: number; reference: string; kind: 'salary' | 'experience'; language: 'ar' | 'en'; addressed_to: string | null; issued_at: string; valid: boolean }

/** Salary and experience letters with a QR verification code (H12). */
function LettersCard({ employee, letters, canIssue, canRevoke, canSalary }: { employee: EmployeeResource; letters: LetterRow[]; canIssue: boolean; canRevoke: boolean; canSalary: boolean }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const l = (field: string) => t(`hr.letters.${field}`);
  const form = useForm({ kind: canSalary ? 'salary' : 'experience', language: locale === 'en' ? 'en' : 'ar', addressed_to: '' });
  const kinds = (['salary', 'experience'] as const).filter((kind) => kind !== 'salary' || canSalary).map((kind) => ({ value: kind, label: t(`hr.letter_kind.${kind}`) }));

  return (
    <Card className="mt-4">
      <CardContent className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-2">
          <h3 className="font-semibold">{l('title')}</h3>
          {canIssue && (
            <Dialog open={open} onOpenChange={setOpen}>
              <DialogTrigger asChild><Button size="sm" variant="outline"><QrCode data-icon="inline-start" />{l('issue')}</Button></DialogTrigger>
              <DialogContent dir={dir}>
                <form className="flex flex-col gap-4" onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employee.id}/letters`), { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset('addressed_to'); } }); }}>
                  <DialogHeader><DialogTitle>{l('issue')}</DialogTitle></DialogHeader>
                  <FormField label={l('kind')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds} /></FormField>
                  <FormField label={l('language')} error={form.errors.language}><SelectInput value={form.data.language} onChange={(value) => form.setData('language', value)} options={[{ value: 'ar', label: 'العربية' }, { value: 'en', label: 'English' }]} /></FormField>
                  <FormField label={l('addressed_to')} hint={l('addressed_to_hint')} error={form.errors.addressed_to}><Input value={form.data.addressed_to} onChange={(event) => form.setData('addressed_to', event.target.value)} /></FormField>
                  <DialogFooter><Button type="submit" disabled={form.processing}>{l('issue')}</Button></DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          )}
        </div>
        {letters.length === 0 && <p className="text-sm text-muted-foreground">{l('none')}</p>}
        <ul className="divide-y">
          {letters.map((letter) => (
            <li key={letter.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
              <div className="min-w-0">
                <div className="font-medium">{t(`hr.letter_kind.${letter.kind}`)} <span dir="ltr" className="font-mono text-xs text-muted-foreground">{letter.reference}</span></div>
                <div className="text-xs text-muted-foreground">{formatDate(letter.issued_at, locale)}{letter.addressed_to && ` · ${letter.addressed_to}`}</div>
              </div>
              <div className="flex items-center gap-1">
                {!letter.valid && <Badge variant="destructive">{l('revoked_badge')}</Badge>}
                <Button asChild size="xs" variant="ghost"><a href={url(`/hr/employees/${employee.id}/letters/${letter.id}/pdf`)} target="_blank" rel="noreferrer"><FileText data-icon="inline-start" />PDF</a></Button>
                {canRevoke && letter.valid && <ConfirmAction href={url(`/hr/employees/${employee.id}/letters/${letter.id}/revoke`)} size="xs" variant="ghost" title={l('revoke')} description={l('revoke_hint')}>{l('revoke')}</ConfirmAction>}
              </div>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

function StatusDialog({ employee, statuses }: { employee: EmployeeResource; statuses: Option[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ status: employee.status, terminated_on: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">{t('hr.employees.fields.status')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(url(`/hr/employees/${employee.id}/status`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader><DialogTitle>{t('hr.employees.fields.status')}</DialogTitle></DialogHeader>
          <FormField label={t('hr.employees.fields.status')} htmlFor="status" error={form.errors.status}>
            <SelectInput id="status" value={form.data.status} onChange={(value) => form.setData('status', value)} options={statuses} />
          </FormField>
          {form.data.status === 'terminated' && (
            <FormField label={t('hr.employees.fields.terminated_on')} htmlFor="terminated_on" error={form.errors.terminated_on}>
              <Input id="terminated_on" type="date" dir="ltr" value={form.data.terminated_on} onChange={(event) => form.setData('terminated_on', event.target.value)} />
            </FormField>
          )}
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export interface PendingChange {
  id: number;
  kind: 'iban' | 'salary';
  summary: Record<string, { from: string | null; to: string | null }>;
  created_at: string;
  requested_by: string | null;
  can_approve: boolean;
  can_reject: boolean;
}

/** Held IBAN/salary changes waiting for a second person (H13). */
function PendingChanges({ changes }: { changes: PendingChange[] }) {
  const t = useT();
  const url = useSuiteUrl();

  return (
    <Card className="mb-4 border-amber-300 bg-amber-50/60">
      <CardContent className="flex flex-col gap-2 py-3 text-sm">
        {changes.map((change) => (
          <div key={change.id} className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <strong>{t(`hr.changes.kind.${change.kind}`)}</strong> · {t('hr.changes.pending')}
              {Object.entries(change.summary).map(([field, value]) => <span key={field} dir="ltr" className="ms-2 text-muted-foreground">{value.from ?? '—'} → {value.to ?? '—'}</span>)}
              {change.requested_by && <span className="ms-2 text-xs text-muted-foreground">{t('hr.changes.requested_by', { name: change.requested_by })}</span>}
            </div>
            <div className="flex gap-2">
              {change.can_approve && <ConfirmAction href={url(`/hr/changes/${change.id}`)} method="put" data={{ decision: 'approve' }} size="sm" title={t('common.actions.approve')}>{t('common.actions.approve')}</ConfirmAction>}
              {change.can_reject && <ConfirmAction href={url(`/hr/changes/${change.id}`)} method="put" data={{ decision: 'reject' }} size="sm" variant="outline" title={t('common.actions.reject')}>{t('common.actions.reject')}</ConfirmAction>}
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

interface SettlementFigures { advances_owed?: string; advances_recovered: string; advances_left: string; net_payable: string }

export interface EndOfServiceData {
  previews: Record<string, SettlementFigures & { eosb: { years: number; monthly_wage: string; full_award: string; award: string } }>;
  settlement: (SettlementFigures & { reason: string; last_day: string; eosb_award: string }) | null;
  can_settle: boolean;
}

/** The award if they left now, net of open advances; recorded once they leave (H10, H16). */
function EndOfServiceCard({ employee, data }: { employee: EmployeeResource; data: EndOfServiceData }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const [reason, setReason] = useState('termination');
  const p = (key: string) => t(`hr.payroll.${key}`);
  const money = (value: string) => formatMoney(value, locale);
  const preview = data.previews[reason];
  const shown = data.settlement ?? { ...preview, eosb_award: preview.eosb.award };

  return (
    <Card className="mt-4">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle className="text-base">{data.settlement ? p('settlement') : `${p('end_of_service')} · ${p('if_leaving')}`}</CardTitle>
        {!data.settlement && <div className="w-48"><SelectInput value={reason} onChange={setReason} options={Object.keys(data.previews).map((key) => ({ value: key, label: t(`hr.payroll.reason.${key}`) }))} /></div>}
      </CardHeader>
      <CardContent>
        <DetailList items={[
          data.settlement ? { label: t(`hr.payroll.reason.${data.settlement.reason}`), value: formatDate(data.settlement.last_day, locale) } : { label: p('years'), value: formatNumber(preview.eosb.years, locale) },
          data.settlement ? null : { label: p('monthly_wage'), value: money(preview.eosb.monthly_wage) },
          { label: p('award'), value: money(shown.eosb_award) },
          { label: p('advances_recovered'), value: money(shown.advances_recovered) },
          Number(shown.advances_left) > 0 ? { label: p('advances_left'), value: <span className="text-red-700">{money(shown.advances_left)}</span> } : null,
          { label: p('net_payable'), value: <strong>{money(shown.net_payable)}</strong> },
        ]} />
        {data.can_settle && (
          <div className="mt-3 flex justify-end">
            <ConfirmAction href={url(`/hr/employees/${employee.id}/final-settlement`)} method="post" data={{ reason }} size="sm" title={p('settle')} description={`${t(`hr.payroll.reason.${reason}`)} · ${money(preview.net_payable)}`}>{p('settle')}</ConfirmAction>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function LoginDialog({ employee, suggestedRole }: { employee: EmployeeResource; suggestedRole: string | null }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const form = useForm({ email: employee.email ?? '', grant_job_role: suggestedRole !== null });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><KeyRound data-icon="inline-start" />{t('hr.employees.link_login')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employee.id}/login`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>{t('hr.employees.link_login')}</DialogTitle>
            <DialogDescription>{t('hr.employees.link_login_text')}</DialogDescription>
          </DialogHeader>
          <FormField label={t('hr.employees.fields.email')} htmlFor="login-email" error={form.errors.email}>
            <Input id="login-email" type="email" dir="ltr" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
          </FormField>
          {suggestedRole && (
            <label className="flex items-start gap-2 text-sm">
              <Checkbox checked={form.data.grant_job_role} onCheckedChange={(checked) => form.setData('grant_job_role', checked === true)} />
              <span>{t('hr.employees.grant_job_role', { role: suggestedRole })}</span>
            </label>
          )}
          <DialogFooter><Button type="submit" disabled={form.processing}>{t('hr.employees.link_login')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ContractDialog({ employee, contract, allowanceTypes }: { employee: EmployeeResource; contract?: ContractResource; allowanceTypes: CatalogItem[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`hr.contracts.fields.${field}`);
  const form = useForm<{ type: string; status: string; starts_on: string; ends_on: string; probation_days: number; basic_salary: string; qiwa_authenticated: boolean; notes: string; allowances: ContractAllowanceLine[] }>({
    type: contract?.type ?? 'open_ended',
    status: contract?.status ?? 'active',
    starts_on: contract?.starts_on ?? new Date().toISOString().slice(0, 10),
    ends_on: contract?.ends_on ?? '',
    probation_days: contract?.probation_days ?? 90,
    basic_salary: contract?.basic_salary ?? '',
    qiwa_authenticated: contract?.qiwa_authenticated ?? false,
    notes: contract?.notes ?? '',
    allowances: contract?.allowances.map((line) => ({ allowance_type_id: line.allowance_type_id, calculation: line.calculation, value: line.value })) ?? [],
  });
  const { data, setData, errors } = form;
  const addAllowance = () => {
    const type = allowanceTypes.find((item) => !data.allowances.some((line) => line.allowance_type_id === item.id)) ?? allowanceTypes[0];
    if (type) setData('allowances', [...data.allowances, { allowance_type_id: type.id, calculation: type.calculation ?? 'fixed', value: type.default_value ?? '0' }]);
  };
  const updateLine = (index: number, patch: Partial<ContractAllowanceLine>) => setData('allowances', data.allowances.map((line, i) => (i === index ? { ...line, ...patch } : line)));

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
    contract ? form.put(url(`/hr/employees/${employee.id}/contracts/${contract.id}`), options) : form.post(url(`/hr/employees/${employee.id}/contracts`), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {contract ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('hr.contracts.new')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{contract ? t('hr.contracts.edit') : t('hr.contracts.new')}</DialogTitle></DialogHeader>
          <FormField label={f('type')} error={errors.type}><SelectInput value={data.type} onChange={(value) => setData('type', value)} options={[{ value: 'open_ended', label: t('hr.contract_type.open_ended') }, { value: 'fixed', label: t('hr.contract_type.fixed') }]} /></FormField>
          <FormField label={f('status')} error={errors.status}><SelectInput value={data.status} onChange={(value) => setData('status', value)} options={['draft', 'active', 'ended'].map((status) => ({ value: status, label: t(`hr.contract_status.${status}`) }))} /></FormField>
          <FormField label={f('starts_on')} error={errors.starts_on}><Input type="date" dir="ltr" value={data.starts_on} onChange={(event) => setData('starts_on', event.target.value)} /></FormField>
          <FormField label={f('ends_on')} error={errors.ends_on}><Input type="date" dir="ltr" value={data.ends_on} onChange={(event) => setData('ends_on', event.target.value)} /></FormField>
          <FormField label={f('probation_days')} error={errors.probation_days}><Input type="number" dir="ltr" value={data.probation_days} onChange={(event) => setData('probation_days', Number(event.target.value))} /></FormField>
          <FormField label={f('basic_salary')} error={errors.basic_salary}><Input type="number" step="0.01" dir="ltr" value={data.basic_salary} onChange={(event) => setData('basic_salary', event.target.value)} /></FormField>
          <label className="flex items-center gap-2 text-sm"><Checkbox checked={data.qiwa_authenticated} onCheckedChange={(state) => setData('qiwa_authenticated', state === true)} />{f('qiwa_authenticated')}</label>
          <div className="sm:col-span-2">
            <div className="mb-2 flex items-center justify-between"><span className="text-sm font-medium">{t('hr.contracts.allowances')}</span><Button type="button" size="xs" variant="outline" onClick={addAllowance}><Plus data-icon="inline-start" />{t('hr.contracts.add_allowance')}</Button></div>
            <div className="flex flex-col gap-2">
              {data.allowances.map((line, index) => (
                <div key={index} className="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
                  <SelectInput value={line.allowance_type_id} onChange={(value) => updateLine(index, { allowance_type_id: Number(value) })} options={allowanceTypes.map((type) => ({ value: type.id, label: type.name }))} />
                  <SelectInput value={line.calculation} onChange={(value) => updateLine(index, { calculation: value as ContractAllowanceLine['calculation'] })} options={[{ value: 'fixed', label: t('hr.allowance_calculation.fixed') }, { value: 'percent_of_basic', label: t('hr.allowance_calculation.percent_of_basic') }]} />
                  <Input type="number" step="0.01" dir="ltr" value={line.value} onChange={(event) => updateLine(index, { value: event.target.value })} />
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => setData('allowances', data.allowances.filter((_, i) => i !== index))} aria-label={t('common.actions.remove')}><Trash2 /></Button>
                </div>
              ))}
            </div>
            {errors.allowances && <p className="mt-1 text-sm text-destructive">{errors.allowances}</p>}
          </div>
          <FormField label={f('notes')} error={errors.notes} className="sm:col-span-2"><Textarea rows={2} value={data.notes} onChange={(event) => setData('notes', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function DocumentDialog({ employee, document, types }: { employee: EmployeeResource; document?: EmployeeDocumentResource; types: CatalogItem[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`hr.documents.fields.${field}`);
  const form = useForm<{ document_type_id: number | ''; number: string; issued_on: string; expires_on: string; notes: string; data: FieldValues; files: File[] }>({
    document_type_id: document?.document_type_id ?? types[0]?.id ?? '',
    number: document?.number ?? '',
    issued_on: document?.issued_on ?? '',
    expires_on: document?.expires_on ?? '',
    notes: document?.notes ?? '',
    data: (document?.data ?? {}) as FieldValues,
    files: [],
  });
  const { data, setData, errors } = form;
  const type = types.find((item) => item.id === Number(data.document_type_id));
  const { ai } = useSuite();
  const [scan, setScan] = useState<{ busy: boolean; error: string | null; done: boolean }>({ busy: false, error: null, done: false });

  async function readWithAi() {
    const body = new FormData();
    body.append('document_type_id', String(data.document_type_id));
    data.files.slice(0, 4).forEach((file) => body.append('files[]', file));
    setScan({ busy: true, error: null, done: false });

    try {
      const read = await postJson<{ number: string | null; issued_on: string | null; expires_on: string | null; data: FieldValues }>(url(`/hr/employees/${employee.id}/documents/scan`), body);
      setData((current) => ({
        ...current,
        number: read.number ?? current.number,
        issued_on: read.issued_on ?? current.issued_on,
        expires_on: read.expires_on ?? current.expires_on,
        data: { ...current.data, ...Object.fromEntries(Object.entries(read.data).filter(([, value]) => value !== null)) },
      }));
      setScan({ busy: false, error: null, done: true });
    } catch (error) {
      setScan({ busy: false, error: (error as Error).message, done: false });
    }
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => setOpen(false) };
    form.post(document ? url(`/hr/employees/${employee.id}/documents/${document.id}`) : url(`/hr/employees/${employee.id}/documents`), options);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {document ? <Button size="icon-sm" variant="ghost" aria-label={t('common.actions.edit')}><Pencil /></Button> : <Button size="sm"><Plus data-icon="inline-start" />{t('hr.documents.new')}</Button>}
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-2xl">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{document ? t('hr.documents.edit') : t('hr.documents.new')}</DialogTitle></DialogHeader>
          <FormField label={f('type')} error={errors.document_type_id} className="sm:col-span-2">
            <SelectInput value={data.document_type_id} onChange={(value) => setData((current) => ({ ...current, document_type_id: Number(value), data: {} }))} options={types.map((item) => ({ value: item.id, label: item.name }))} />
          </FormField>
          {type?.has_number !== false && <FormField label={f('number')} error={errors.number}><Input dir="ltr" value={data.number} onChange={(event) => setData('number', event.target.value)} /></FormField>}
          <FormField label={f('issued_on')} error={errors.issued_on}><Input type="date" dir="ltr" value={data.issued_on} onChange={(event) => setData('issued_on', event.target.value)} /></FormField>
          {type?.has_expiry !== false && <FormField label={f('expires_on')} error={errors.expires_on}><Input type="date" dir="ltr" value={data.expires_on} onChange={(event) => setData('expires_on', event.target.value)} /></FormField>}
          <DynamicFields fields={type?.fields ?? []} value={data.data} onChange={(next) => setData('data', next)} errors={errors as Record<string, string>} />
          <FormField label={t('hr.documents.add_files')} error={errors.files} className="sm:col-span-2">
            <Input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(event) => setData('files', Array.from(event.target.files ?? []))} />
          </FormField>
          {ai && data.files.length > 0 && (
            <div className="flex flex-wrap items-center gap-2 rounded-md border border-dashed p-2 text-sm sm:col-span-2">
              <Button type="button" size="sm" variant="outline" onClick={readWithAi} disabled={scan.busy || data.document_type_id === ''}>
                <Sparkles data-icon="inline-start" />{scan.busy ? t('hr.documents.ai_reading') : t('hr.documents.ai_read')}
              </Button>
              <span className={scan.error ? 'text-destructive' : 'text-muted-foreground'}>{scan.error ?? (scan.done ? t('hr.documents.ai_done') : t('hr.documents.ai_hint'))}</span>
            </div>
          )}
          <FormField label={f('notes')} error={errors.notes} className="sm:col-span-2"><Textarea rows={2} value={data.notes} onChange={(event) => setData('notes', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function MovementDialog({ employee, branches }: { employee: EmployeeResource; branches: BranchOption[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const f = (field: string) => t(`hr.movements.fields.${field}`);
  const form = useForm({ type: 'transfer', to_branch_id: '' as number | '', starts_on: new Date().toISOString().slice(0, 10), ends_on: '', reason: '' });
  const { data, setData, errors } = form;
  const targets = branches.filter((branch) => branch.value !== employee.branch_id && (data.type === 'secondment' || branch.company_id === employee.company_id));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm"><ArrowLeftRight data-icon="inline-start" />{t('hr.movements.title')}</Button></DialogTrigger>
      <DialogContent dir={dir}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/employees/${employee.id}/movements`), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="grid gap-4 sm:grid-cols-2">
          <DialogHeader className="sm:col-span-2"><DialogTitle>{t('hr.movements.title')}</DialogTitle></DialogHeader>
          <FormField label={f('type')} error={errors.type} className="sm:col-span-2">
            <SelectInput value={data.type} onChange={(value) => setData((current) => ({ ...current, type: value, to_branch_id: '' }))} options={[{ value: 'transfer', label: t('hr.movement_type.transfer') }, { value: 'secondment', label: t('hr.movement_type.secondment') }]} />
          </FormField>
          <FormField label={f('to_branch')} error={errors.to_branch_id} className="sm:col-span-2"><SelectInput value={data.to_branch_id} onChange={(value) => setData('to_branch_id', Number(value))} options={targets} /></FormField>
          <FormField label={f('starts_on')} error={errors.starts_on}><Input type="date" dir="ltr" value={data.starts_on} onChange={(event) => setData('starts_on', event.target.value)} /></FormField>
          {data.type === 'secondment' && <FormField label={f('ends_on')} error={errors.ends_on}><Input type="date" dir="ltr" value={data.ends_on} onChange={(event) => setData('ends_on', event.target.value)} /></FormField>}
          <FormField label={f('reason')} error={errors.reason} className="sm:col-span-2"><Input value={data.reason} onChange={(event) => setData('reason', event.target.value)} /></FormField>
          <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function EmployeeShow({ employee, company, branch, login, suggested_role: suggestedRole, end_of_service: endOfService, pending_changes: pendingChanges, device, contracts, documents, movements, audit, statuses, branches, allowance_types: allowanceTypes, document_types: documentTypes, pay, leave, letters, can_salary_letter: canSalaryLetter }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const f = (field: string) => t(`hr.employees.fields.${field}`);
  const { features } = useSuite();
  const can = employee.can;

  return (
    <SuiteLayout title={employee.name}>
      <PageHeader
        title={employee.name}
        description={`${employee.employee_number} · ${employee.job ?? '—'} · ${company ?? ''} / ${branch ?? ''}`}
        actions={(
          <>
            {can.update && <StatusDialog employee={employee} statuses={statuses} />}
            {can.link_login && !employee.user_id && <LoginDialog employee={employee} suggestedRole={suggestedRole} />}
            {can.update && <Button asChild size="sm" variant="outline"><Link href={url(`/hr/employees/${employee.id}/edit`)}><Pencil data-icon="inline-start" />{t('common.actions.edit')}</Link></Button>}
          </>
        )}
      >
        <div className="mt-2 flex items-center gap-2">
          <StatusBadge group="employee_status" value={employee.status} file="hr" />
          {login ? <Badge variant="outline" className="gap-1"><KeyRound className="size-3" /><span dir="ltr">{login.email}</span>{login.pending && ` · ${t('suite.access.pending')}`}</Badge> : <Badge variant="outline" className="text-muted-foreground">{t('hr.employees.no_login')}</Badge>}
          {device && (
            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
              <Smartphone className="size-3" />{device.model ?? device.platform ?? '—'} · {formatDate(device.bound_at, locale)}
              {can.reset_device && <ConfirmAction href={url(`/hr/employees/${employee.id}/device`)} method="delete" variant="ghost" size="xs" title={t('hr.attendance.reset_device')} description={t('hr.attendance.device_reset')} confirmLabel={t('hr.attendance.reset_device')}>{t('hr.attendance.reset_device')}</ConfirmAction>}
            </span>
          )}
        </div>
      </PageHeader>

      {pendingChanges.length > 0 && <PendingChanges changes={pendingChanges} />}
      <Tabs defaultValue="profile">
        <TabsList>
          <TabsTrigger value="profile">{t('hr.employees.tabs.profile')}</TabsTrigger>
          {can.view_salaries && <TabsTrigger value="contract">{t('hr.employees.tabs.contract')}</TabsTrigger>}
          {can.view_documents && <TabsTrigger value="documents">{t('hr.employees.tabs.documents')}</TabsTrigger>}
          {can.view_movements && <TabsTrigger value="movements">{t('hr.employees.tabs.movements')}</TabsTrigger>}
          {pay && <TabsTrigger value="pay">{t('hr.pay.title')}</TabsTrigger>}
          {leave && <TabsTrigger value="leave">{t('hr.leave.title')}</TabsTrigger>}
          <TabsTrigger value="audit">{t('hr.employees.tabs.audit')}</TabsTrigger>
        </TabsList>

        <TabsContent value="profile">
          <Card><CardContent>
            <DetailList items={[
              { label: f('name_ar'), value: employee.name_ar },
              { label: f('name_en'), value: <bdi>{employee.name_en}</bdi> },
              { label: f('section'), value: employee.section },
              { label: f('job'), value: employee.job },
              { label: f('manager'), value: employee.manager },
              { label: f('hired_on'), value: formatDate(employee.hired_on, locale) },
              { label: f('email'), value: employee.email && <span dir="ltr">{employee.email}</span> },
              { label: f('phone'), value: employee.phone && <span dir="ltr">{employee.phone}</span> },
              { label: f('id_type'), value: employee.id_type && t(`hr.id_type.${employee.id_type}`) },
              { label: f('national_id'), value: employee.national_id ? <span dir="ltr">{employee.national_id}</span> : (can.view_national_id ? null : '••••') },
              { label: f('nationality'), value: employee.nationality },
              { label: f('gender'), value: employee.gender && t(`hr.gender.${employee.gender}`) },
              { label: f('birth_date'), value: formatDate(employee.birth_date, locale) },
              { label: f('iban'), value: employee.iban && <span dir="ltr">{employee.iban}</span> },
              employee.terminated_on ? { label: f('terminated_on'), value: formatDate(employee.terminated_on, locale) } : null,
              { label: f('notes'), value: employee.notes, wide: true },
            ]} />
          </CardContent></Card>
          {endOfService && <EndOfServiceCard employee={employee} data={endOfService} />}
        </TabsContent>

        {can.view_salaries && (
          <TabsContent value="contract">
            <div className="mb-3 flex justify-end">{can.manage_contracts && <ContractDialog employee={employee} allowanceTypes={allowanceTypes} />}</div>
            {contracts.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">{t('hr.contracts.none')}</CardContent></Card>}
            <div className="flex flex-col gap-3">
              {contracts.map((contract) => (
                <Card key={contract.id}>
                  <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-base"><FileText className="size-4 text-brass" />{t(`hr.contract_type.${contract.type}`)}<StatusBadge group="contract_status" value={contract.status} file="hr" /></CardTitle>
                    {can.manage_contracts && <ContractDialog employee={employee} contract={contract} allowanceTypes={allowanceTypes} />}
                  </CardHeader>
                  <CardContent>
                    <DetailList items={[
                      { label: t('hr.contracts.fields.starts_on'), value: formatDate(contract.starts_on, locale) },
                      { label: t('hr.contracts.fields.ends_on'), value: formatDate(contract.ends_on, locale) },
                      { label: t('hr.contracts.fields.probation_days'), value: formatNumber(contract.probation_days, locale) },
                      { label: t('hr.contracts.fields.basic_salary'), value: formatMoney(contract.basic_salary, locale) },
                      ...contract.allowances.map((line) => ({ label: line.name ?? '', value: `${formatMoney(line.amount, locale)}${line.calculation === 'percent_of_basic' ? ` (${line.value}%)` : ''}` })),
                      { label: t('hr.contracts.total'), value: <strong>{formatMoney(contract.total, locale)}</strong> },
                    ]} />
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>
        )}

        {can.view_documents && (
          <TabsContent value="documents">
            <div className="mb-3 flex justify-end">{can.manage_documents && <DocumentDialog employee={employee} types={documentTypes} />}</div>
            {documents.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">{t('hr.documents.none')}</CardContent></Card>}
            <div className="grid gap-3 md:grid-cols-2">
              {documents.map((document) => (
                <Card key={document.id}>
                  <CardHeader className="flex flex-row items-start justify-between">
                    <div>
                      <CardTitle className="text-base">{document.type}</CardTitle>
                      {document.expires_on && (
                        <p className={`mt-1 text-xs ${(document.days_left ?? 0) < 0 ? 'text-destructive' : (document.days_left ?? 0) < 30 ? 'text-amber-700' : 'text-muted-foreground'}`}>
                          {(document.days_left ?? 0) < 0 ? t('hr.documents.expired') : t('hr.documents.expires_in', { when: formatDate(document.expires_on, locale) })}
                        </p>
                      )}
                    </div>
                    <div className="flex gap-1">
                      {can.manage_documents && <DocumentDialog employee={employee} document={document} types={documentTypes} />}
                      {can.manage_documents && (
                        <ConfirmAction href={url(`/hr/employees/${employee.id}/documents/${document.id}`)} method="delete" variant="ghost" size="icon-sm" title={t('hr.documents.deleted')} confirmLabel={t('common.actions.remove')}><Trash2 className="text-destructive" /></ConfirmAction>
                      )}
                    </div>
                  </CardHeader>
                  <CardContent>
                    <DetailList items={[
                      document.number ? { label: t('hr.documents.fields.number'), value: <span dir="ltr">{document.number}</span> } : null,
                      { label: t('hr.documents.fields.issued_on'), value: formatDate(document.issued_on, locale) },
                      ...Object.entries(document.data).map(([key, value]) => ({ label: key, value: String(value ?? '') })),
                    ]} />
                    {document.files.length > 0 && (
                      <ul className="mt-3 flex flex-wrap gap-2">
                        {document.files.map((file) => (
                          <li key={file.id}>
                            <a href={url(`/hr/employees/${employee.id}/documents/${document.id}/files/${file.id}`)} className="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs hover:bg-muted" target="_blank" rel="noreferrer">
                              <Paperclip className="size-3" />{file.name}
                            </a>
                          </li>
                        ))}
                      </ul>
                    )}
                  </CardContent>
                </Card>
              ))}
            </div>
            {features.includes('hr.letters') && <LettersCard employee={employee} letters={letters} canIssue={Boolean(can.manage_documents)} canRevoke={Boolean(can.manage_documents)} canSalary={canSalaryLetter} />}
          </TabsContent>
        )}

        {can.view_movements && (
          <TabsContent value="movements">
            <div className="mb-3 flex justify-end">{can.manage_movements && <MovementDialog employee={employee} branches={branches} />}</div>
            {movements.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">{t('hr.movements.none')}</CardContent></Card>}
            <ul className="flex flex-col gap-2">
              {movements.map((movement) => (
                <li key={movement.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-card px-4 py-3 text-sm">
                  <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge group="movement_type" value={movement.type} file="hr" />
                    <span>{movement.from_branch} → {movement.to_branch}</span>
                    <span className="text-muted-foreground">{formatDate(movement.starts_on, locale)}{movement.ends_on && ` → ${formatDate(movement.ends_on, locale)}`}</span>
                    <StatusBadge group="movement_status" value={movement.status} file="hr" />
                  </div>
                  {movement.cancellable && can.manage_movements && (
                    <ConfirmAction href={url(`/hr/employees/${employee.id}/movements/${movement.id}`)} method="delete" variant="ghost" size="xs" title={t('hr.movements.cancel_title')} confirmLabel={t('hr.movements.cancel')}>{t('hr.movements.cancel')}</ConfirmAction>
                  )}
                </li>
              ))}
            </ul>
          </TabsContent>
        )}

        {pay && <TabsContent value="pay"><PayTab employeeId={employee.id} pay={pay} can={can} /></TabsContent>}
        {leave && <TabsContent value="leave"><LeaveTab employeeId={employee.id} leave={leave} can={can} /></TabsContent>}

        <TabsContent value="audit">
          <Card><CardContent>
            <ul className="divide-y text-sm">
              {audit.map((entry) => (
                <li key={entry.id} className="flex flex-wrap items-baseline gap-2 py-2">
                  <span className="font-medium">{entry.event}</span>
                  <span className="text-muted-foreground">{entry.actor ?? '—'} · {new Date(entry.at).toLocaleString(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB')}</span>
                  {entry.changes && <code className="text-xs text-muted-foreground" dir="ltr">{JSON.stringify(entry.changes)}</code>}
                </li>
              ))}
            </ul>
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </SuiteLayout>
  );
}
