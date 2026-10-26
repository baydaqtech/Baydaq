import { Link, router, useForm } from '@inertiajs/react';
import { Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
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
import { formatDate, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface PrivacyRequest {
  id: number; kind: string; subject: string; customer_id: number | null; name: string; contact: string; status: string;
  details: string | null; outcome: string | null; received_on: string; due_on: string; overdue: boolean;
}

interface Props {
  requests: PrivacyRequest[];
  counts: { open: number; overdue: number; consented: number; customers: number };
  filters: { status: string };
  kinds: string[];
  subjects: string[];
  tips: string[];
}

function NewRequest({ kinds, subjects }: { kinds: string[]; subjects: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const p = (key: string) => t(`suite.privacy.${key}`);
  const [open, setOpen] = useState(false);
  const form = useForm({ kind: 'access', subject: 'customer', name: '', contact: '', received_on: new Date().toISOString().slice(0, 10), details: '' });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button><Plus />{p('add')}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{p('add')}</DialogTitle></DialogHeader>
        <form id="privacy-request" className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); form.post(url('/privacy'), { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } }); }}>
          <FormField label={p('kind')} error={form.errors.kind}><SelectInput value={form.data.kind} onChange={(value) => form.setData('kind', value)} options={kinds.map((value) => ({ value, label: p(`kinds.${value}`) }))} /></FormField>
          <FormField label={p('subject')} error={form.errors.subject}><SelectInput value={form.data.subject} onChange={(value) => form.setData('subject', value)} options={subjects.map((value) => ({ value, label: p(`subjects.${value}`) }))} /></FormField>
          <FormField label={p('name')} error={form.errors.name}><Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></FormField>
          <FormField label={p('contact')} error={form.errors.contact}><Input dir="ltr" value={form.data.contact} onChange={(event) => form.setData('contact', event.target.value)} /></FormField>
          <FormField label={p('received_on')} error={form.errors.received_on}><Input type="date" dir="ltr" value={form.data.received_on} onChange={(event) => form.setData('received_on', event.target.value)} /></FormField>
          <FormField label={p('details')} error={form.errors.details} className="sm:col-span-2"><Textarea rows={3} value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} /></FormField>
        </form>
        <DialogFooter><Button type="submit" form="privacy-request" disabled={form.processing}>{p('add')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** The PDPL request register with its 30-day clock, and good-practice reminders. */
export default function Privacy({ requests, counts, filters, kinds, subjects, tips }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const p = (key: string) => t(`suite.privacy.${key}`);

  const close = (request: PrivacyRequest, status: 'done' | 'refused') => {
    const outcome = window.prompt(p('outcome')) ?? '';
    router.put(url(`/privacy/${request.id}`), { status, outcome }, { preserveScroll: true });
  };

  return (
    <SuiteLayout title={p('title')}>
      <PageHeader title={p('title')} description={p('lede')} actions={<NewRequest kinds={kinds} subjects={subjects} />} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {(['open', 'overdue', 'consented', 'customers'] as const).map((key) => (
          <Card key={key}><CardContent>
            <div className="text-sm text-muted-foreground">{p(key)}</div>
            <div className={cn('text-2xl font-semibold', key === 'overdue' && counts.overdue > 0 && 'text-destructive')}>{formatNumber(counts[key], locale)}</div>
          </CardContent></Card>
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
        <Card>
          <CardHeader className="flex flex-row flex-wrap gap-1">
            {(['open', 'done', 'refused', 'all'] as const).map((status) => (
              <Button key={status} size="sm" variant={filters.status === status ? 'secondary' : 'ghost'} asChild><Link href={url(`/privacy?status=${status}`)}>{p(`statuses.${status}`)}</Link></Button>
            ))}
          </CardHeader>
          <CardContent className="divide-y">
            {requests.length === 0 && <p className="text-sm text-muted-foreground">{p('none')}</p>}
            {requests.map((request) => (
              <div key={request.id} className="flex flex-col gap-1 py-3 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{request.name}</span>
                    <span className="text-muted-foreground" dir="ltr">{request.contact}</span>
                    <Badge variant="outline">{p(`kinds.${request.kind}`)}</Badge>
                    <Badge variant="secondary">{p(`subjects.${request.subject}`)}</Badge>
                  </span>
                  <span className={cn('text-xs', request.overdue ? 'font-medium text-destructive' : 'text-muted-foreground')}>{p('due_on')}: {formatDate(request.due_on, locale)}</span>
                </div>
                {request.details && <p className="text-muted-foreground">{request.details}</p>}
                {request.outcome && <p><span className="font-medium">{p('outcome')}: </span>{request.outcome}</p>}
                <div className="flex flex-wrap gap-2">
                  {request.customer_id && <Button size="sm" variant="ghost" asChild><Link href={url(`/customers/${request.customer_id}`)}>{t('suite.customers.title')}</Link></Button>}
                  {request.status === 'open'
                    ? <><Button size="sm" onClick={() => close(request, 'done')}>{p('mark_done')}</Button><Button size="sm" variant="ghost" onClick={() => close(request, 'refused')}>{p('refuse')}</Button></>
                    : <Badge>{p(`statuses.${request.status}`)}</Badge>}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-4" />{p('tips_title')}</CardTitle></CardHeader>
          <CardContent>
            <ul className="list-disc space-y-2 ps-5 text-sm text-muted-foreground">{tips.map((tip) => <li key={tip}>{tip}</li>)}</ul>
          </CardContent>
        </Card>
      </div>
    </SuiteLayout>
  );
}
