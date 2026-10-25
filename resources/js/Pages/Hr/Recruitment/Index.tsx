import { Link, router, useForm } from '@inertiajs/react';
import { Copy, FileDown, Plus, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface Opening {
  id: number; title: string; title_ar: string; title_en: string; description: string | null; positions: number; status: string; closes_on: string | null;
  job_id: number | null; branch: string | null; branch_id: number; candidates: number; hired: number; link: string;
}

interface Candidate {
  id: number; name: string; phone: string; email: string | null; nationality: string | null; stage: string; rating: number | null; notes: string | null;
  source: string; employee_id: number | null; interview_at: string | null; offer_salary: string | null; has_cv: boolean; applied_at: string;
}

interface Props {
  openings: Opening[];
  selected: number | null;
  candidates: Candidate[];
  stages: string[];
  branches: (Option & { company_id: number })[];
  jobs: Option[];
  can_manage: boolean;
  can_hire: boolean;
}

function OpeningDialog({ opening, branches, jobs }: { opening?: Opening; branches: Props['branches']; jobs: Option[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recruitment.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({
    title_ar: opening?.title_ar ?? '', title_en: opening?.title_en ?? '', description: opening?.description ?? '', job_id: opening?.job_id ? String(opening.job_id) : '',
    branch_id: String(opening?.branch_id ?? branches[0]?.value ?? ''), positions: String(opening?.positions ?? 1), status: opening?.status ?? 'open', closes_on: opening?.closes_on ?? '',
  });
  const submit = () => (opening ? form.put(url(`/hr/recruitment/openings/${opening.id}`), { preserveScroll: true, onSuccess: () => setOpen(false) }) : form.post(url('/hr/recruitment/openings'), { onSuccess: () => setOpen(false) }));

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{opening ? <Button size="sm" variant="ghost">{r('edit_opening')}</Button> : <Button><Plus />{r('new_opening')}</Button>}</DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{opening ? r('edit_opening') : r('new_opening')}</DialogTitle></DialogHeader>
        <form id="opening-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); submit(); }}>
          <FormField label={r('title_ar')} error={form.errors.title_ar}><Input value={form.data.title_ar} onChange={(event) => form.setData('title_ar', event.target.value)} /></FormField>
          <FormField label={r('title_en')} error={form.errors.title_en}><Input dir="ltr" value={form.data.title_en} onChange={(event) => form.setData('title_en', event.target.value)} /></FormField>
          {!opening && <FormField label={r('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>}
          <FormField label={r('job')} error={form.errors.job_id}><SelectInput value={form.data.job_id} onChange={(value) => form.setData('job_id', value)} options={jobs} emptyLabel="—" /></FormField>
          <FormField label={r('positions')} error={form.errors.positions}><Input type="number" min="1" dir="ltr" value={form.data.positions} onChange={(event) => form.setData('positions', event.target.value)} /></FormField>
          <FormField label={r('closes_on')} error={form.errors.closes_on}><Input type="date" dir="ltr" value={form.data.closes_on} onChange={(event) => form.setData('closes_on', event.target.value)} /></FormField>
          <FormField label={r('status')} error={form.errors.status}><SelectInput value={form.data.status} onChange={(value) => form.setData('status', value)} options={['draft', 'open', 'closed'].map((value) => ({ value, label: r(`statuses.${value}`) }))} /></FormField>
          <FormField label={r('description')} error={form.errors.description} className="sm:col-span-2"><Textarea rows={5} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="opening-form" disabled={form.processing}>{r('save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function AddCandidate({ opening }: { opening: Opening }) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recruitment.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm<{ name: string; phone: string; email: string; nationality: string; source: string; cv: File | null }>({ name: '', phone: '', email: '', nationality: '', source: 'manual', cv: null });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline"><Plus />{r('add_candidate')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{r('add_candidate')}</DialogTitle></DialogHeader>
        <form id="candidate-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/recruitment/openings/${opening.id}/candidates`), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}>
          <FormField label={r('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={r('phone')} error={form.errors.phone}><Input dir="ltr" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></FormField>
          <FormField label={r('email')} error={form.errors.email}><Input dir="ltr" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></FormField>
          <FormField label={r('nationality')} error={form.errors.nationality}><Input dir="ltr" maxLength={2} value={form.data.nationality} onChange={(event) => form.setData('nationality', event.target.value.toUpperCase())} /></FormField>
          <FormField label={r('cv')} error={form.errors.cv} className="sm:col-span-2"><Input type="file" accept=".pdf,.doc,.docx,image/*" onChange={(event) => form.setData('cv', event.target.files?.[0] ?? null)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="candidate-form" disabled={form.processing}>{r('save')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function HireDialog({ candidate, opening, branches }: { candidate: Candidate; opening: Opening; branches: Props['branches'] }) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recruitment.${key}`);
  const companyId = branches.find((branch) => branch.value === opening.branch_id)?.company_id;
  const form = useForm({ employee_number: '', branch_id: String(opening.branch_id), hired_on: new Date().toISOString().slice(0, 10), basic_salary: candidate.offer_salary ?? '', probation_days: '90', national_id: '' });

  return (
    <Dialog>
      <DialogTrigger asChild><Button size="sm"><UserCheck />{r('hire')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{r('hire')} · {candidate.name}</DialogTitle></DialogHeader>
        <form id={`hire-${candidate.id}`} className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url(`/hr/recruitment/candidates/${candidate.id}/hire`)); }}>
          <FormField label={r('employee_number')} error={form.errors.employee_number}><Input dir="ltr" value={form.data.employee_number} onChange={(event) => form.setData('employee_number', event.target.value)} /></FormField>
          <FormField label={r('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches.filter((branch) => branch.company_id === companyId)} /></FormField>
          <FormField label={r('hired_on')} error={form.errors.hired_on}><Input type="date" dir="ltr" value={form.data.hired_on} onChange={(event) => form.setData('hired_on', event.target.value)} /></FormField>
          <FormField label={r('basic_salary')} error={form.errors.basic_salary}><Input type="number" dir="ltr" value={form.data.basic_salary} onChange={(event) => form.setData('basic_salary', event.target.value)} /></FormField>
          <FormField label={r('probation_days')} error={form.errors.probation_days}><Input type="number" min="0" max="180" dir="ltr" value={form.data.probation_days} onChange={(event) => form.setData('probation_days', event.target.value)} /></FormField>
          <FormField label={r('national_id')} error={form.errors.national_id}><Input dir="ltr" value={form.data.national_id} onChange={(event) => form.setData('national_id', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form={`hire-${candidate.id}`} disabled={form.processing || form.data.employee_number === ''}>{r('hire')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CandidateCard({ candidate, opening, stages, canManage, canHire, branches }: { candidate: Candidate; opening: Opening; stages: string[]; canManage: boolean; canHire: boolean; branches: Props['branches'] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recruitment.${key}`);
  const update = (data: Record<string, string | number>) => router.put(url(`/hr/recruitment/candidates/${candidate.id}`), data, { preserveScroll: true });

  return (
    <div className="flex flex-col gap-2 rounded-lg border bg-card p-3 text-sm">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="font-medium">{candidate.name}</div>
          <div className="text-xs text-muted-foreground" dir="ltr">{candidate.phone}{candidate.nationality ? ` · ${candidate.nationality}` : ''}</div>
        </div>
        <Badge variant="outline" className="shrink-0">{r(`sources.${candidate.source}`)}</Badge>
      </div>
      <div className="flex items-center gap-0.5" dir="ltr">
        {[1, 2, 3, 4, 5].map((star) => (
          <button key={star} type="button" disabled={!canManage || candidate.stage === 'hired'} onClick={() => update({ rating: star })} className={cn('text-lg leading-none', (candidate.rating ?? 0) >= star ? 'text-amber-500' : 'text-muted-foreground/40')}>★</button>
        ))}
      </div>
      {candidate.interview_at && <div className="text-xs">{r('interview_at')}: {formatDate(candidate.interview_at, locale)}</div>}
      <div className="flex flex-wrap items-center gap-1">
        {candidate.has_cv && <Button size="sm" variant="ghost" asChild><a href={url(`/hr/recruitment/candidates/${candidate.id}/cv`)}><FileDown />{r('cv')}</a></Button>}
        {candidate.employee_id && <Button size="sm" variant="ghost" asChild><Link href={url(`/hr/employees/${candidate.employee_id}`)}>{t('hr.violations.employee')}</Link></Button>}
        {canManage && candidate.stage !== 'hired' && (
          <SelectInput className="h-8 w-32" value={candidate.stage} onChange={(stage) => update({ stage })} options={stages.filter((stage) => stage !== 'hired').map((value) => ({ value, label: r(`stages.${value}`) }))} />
        )}
        {canHire && candidate.stage === 'offer' && <HireDialog candidate={candidate} opening={opening} branches={branches} />}
      </div>
    </div>
  );
}

/** Openings and each one's candidates as a board of hiring stages. */
export default function RecruitmentIndex({ openings, selected, candidates, stages, branches, jobs, can_manage: canManage, can_hire: canHire }: Props) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recruitment.${key}`);
  const opening = openings.find((item) => item.id === selected);

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('lede')} actions={canManage && branches.length > 0 ? <OpeningDialog branches={branches} jobs={jobs} /> : null} />
      <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
        <Card>
          <CardHeader><CardTitle className="text-base">{r('openings')}</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-1">
            {openings.length === 0 && <p className="text-sm text-muted-foreground">{r('no_openings')}</p>}
            {openings.map((item) => (
              <Link key={item.id} href={url(`/hr/recruitment?opening=${item.id}`)} className={cn('rounded-md px-2 py-2 text-sm hover:bg-muted', item.id === selected && 'bg-muted')}>
                <div className="flex items-center justify-between gap-2"><span className="font-medium">{item.title}</span><Badge variant={item.status === 'open' ? 'default' : 'outline'}>{r(`statuses.${item.status}`)}</Badge></div>
                <div className="text-xs text-muted-foreground">{item.branch} · {item.hired}/{item.positions} · {item.candidates} {r('candidates')}</div>
              </Link>
            ))}
          </CardContent>
        </Card>
        {opening && (
          <div className="flex min-w-0 flex-col gap-3">
            <Card>
              <CardContent className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                  <div className="font-semibold">{opening.title}</div>
                  <div className="truncate text-xs text-muted-foreground" dir="ltr">{opening.link}</div>
                </div>
                <div className="flex gap-1">
                  <Button size="sm" variant="outline" onClick={() => { void navigator.clipboard?.writeText(opening.link); toast.success(r('copied')); }}><Copy />{r('copy_link')}</Button>
                  {canManage && <AddCandidate opening={opening} />}
                  {canManage && <OpeningDialog opening={opening} branches={branches} jobs={jobs} />}
                </div>
              </CardContent>
            </Card>
            {candidates.length === 0 && <p className="text-sm text-muted-foreground">{r('no_candidates')}</p>}
            <div className="flex gap-3 overflow-x-auto pb-2">
              {stages.map((stage) => (
                <div key={stage} className="flex w-60 shrink-0 flex-col gap-2 rounded-lg bg-muted/40 p-2">
                  <div className="flex items-center justify-between px-1 text-sm font-medium">{r(`stages.${stage}`)}<span className="text-muted-foreground">{candidates.filter((candidate) => candidate.stage === stage).length}</span></div>
                  {candidates.filter((candidate) => candidate.stage === stage).map((candidate) => (
                    <CandidateCard key={candidate.id} candidate={candidate} opening={opening} stages={stages} canManage={canManage} canHire={canHire} branches={branches} />
                  ))}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </SuiteLayout>
  );
}
