import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Camera, CheckSquare, Clock, MessageSquare, Pencil, Plus, Trash2, X } from 'lucide-react';
import ConfirmAction from '@/components/platform/ConfirmAction';
import FilterBar from '@/components/platform/FilterBar';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuiteUrl } from '@/lib/suite';
import { useFilters, type Filters } from '@/lib/useFilters';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/resources';

interface ChecklistItem { text: string; done: boolean }
interface Comment { id: number; body: string; author: string | null; created_at: string }
interface TaskRow {
  id: number; title: string; description: string | null; status: string; priority: string;
  assignee_id: number | null; assignee: string | null; branch_id: number; branch: string | null;
  checklist: ChecklistItem[]; needs_photo: boolean; has_photo: boolean; due_at: string | null; overdue: boolean; completed_at: string | null; comments: Comment[];
}
interface Props { tasks: TaskRow[]; filters: Filters; branches: Option[]; employees: (Option & { branch_id: number })[]; priorities: Option[]; statuses: Option[] }

const PRIORITY: Record<string, string> = { urgent: 'bg-red-100 text-red-800', high: 'bg-amber-100 text-amber-800', normal: 'bg-muted text-foreground', low: 'bg-muted text-muted-foreground' };
const COLUMNS = ['todo', 'in_progress', 'done'] as const;

/** A datetime-local value in the browser's time from an ISO string. */
function localInput(iso: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function TaskForm({ task, branches, employees, priorities, onDone }: { task?: TaskRow; branches: Option[]; employees: Props['employees']; priorities: Option[]; onDone: () => void }) {
  const t = useT();
  const url = useSuiteUrl();
  const f = (field: string) => t(`hr.tasks.fields.${field}`);
  const form = useForm({
    title: task?.title ?? '', description: task?.description ?? '', priority: task?.priority ?? 'normal',
    assignee_id: task?.assignee_id ? String(task.assignee_id) : '', branch_id: String(task?.branch_id ?? branches[0]?.value ?? ''),
    due_at: localInput(task?.due_at ?? null), needs_photo: task?.needs_photo ?? false, checklist: task?.checklist ?? [] as ChecklistItem[],
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.transform((data) => ({ ...data, assignee_id: data.assignee_id || null, due_at: data.due_at ? new Date(data.due_at).toISOString() : null }));
    const options = { preserveScroll: true, onSuccess: onDone };
    task ? form.put(url(`/hr/tasks/${task.id}`), options) : form.post(url('/hr/tasks'), options);
  }

  const setItem = (index: number, text: string) => form.setData('checklist', form.data.checklist.map((item, i) => (i === index ? { ...item, text } : item)));

  return (
    <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
      <FormField label={f('title')} error={form.errors.title} className="sm:col-span-2"><Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></FormField>
      <FormField label={f('description')} error={form.errors.description} className="sm:col-span-2"><Textarea rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></FormField>
      <FormField label={f('assignee')} error={form.errors.assignee_id}><SelectInput value={form.data.assignee_id} onChange={(value) => form.setData('assignee_id', value)} options={employees} emptyLabel={t('hr.tasks.unassigned')} /></FormField>
      {form.data.assignee_id === '' && <FormField label={f('branch')} error={form.errors.branch_id}><SelectInput value={form.data.branch_id} onChange={(value) => form.setData('branch_id', value)} options={branches} /></FormField>}
      <FormField label={f('priority')} error={form.errors.priority}><SelectInput value={form.data.priority} onChange={(value) => form.setData('priority', value)} options={priorities} /></FormField>
      <FormField label={f('due_at')} error={form.errors.due_at}><Input type="datetime-local" dir="ltr" value={form.data.due_at} onChange={(event) => form.setData('due_at', event.target.value)} /></FormField>
      <div className="flex flex-col gap-2 sm:col-span-2">
        <span className="text-sm font-medium">{f('checklist')}</span>
        {form.data.checklist.map((item, index) => (
          <div key={index} className="flex gap-2">
            <Input value={item.text} onChange={(event) => setItem(index, event.target.value)} />
            <Button type="button" size="icon-sm" variant="ghost" onClick={() => form.setData('checklist', form.data.checklist.filter((_, i) => i !== index))}><X /></Button>
          </div>
        ))}
        <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('checklist', [...form.data.checklist, { text: '', done: false }])}><Plus data-icon="inline-start" />{t('hr.tasks.add_item')}</Button>
      </div>
      <label className="flex items-center gap-2 text-sm sm:col-span-2"><Checkbox checked={form.data.needs_photo} onCheckedChange={(state) => form.setData('needs_photo', state === true)} />{f('needs_photo')}</label>
      <DialogFooter className="sm:col-span-2"><Button type="submit" disabled={form.processing}>{t('common.actions.save')}</Button></DialogFooter>
    </form>
  );
}

function TaskDetail({ task, props }: { task: TaskRow; props: Props }) {
  const t = useT();
  const { dir, locale } = useLocale();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(false);
  const comment = useForm({ body: '' });
  const move = (status: string) => router.post(url(`/hr/tasks/${task.id}/status`), { status }, { preserveScroll: true });
  const done = task.checklist.filter((item) => item.done).length;

  return (
    <Dialog open={open} onOpenChange={(value) => { setOpen(value); setEditing(false); }}>
      <DialogTrigger asChild>
        <button type="button" className={cn('w-full rounded-lg border bg-card p-3 text-start text-sm shadow-xs transition hover:border-brass', task.overdue && 'border-red-300')}>
          <div className="flex items-start justify-between gap-2">
            <span className="font-medium">{task.title}</span>
            <Badge className={cn('border-transparent', PRIORITY[task.priority])}>{t(`hr.task_priority.${task.priority}`)}</Badge>
          </div>
          <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span>{task.assignee ?? t('hr.tasks.unassigned')} · {task.branch}</span>
            {task.due_at && <span className={cn('flex items-center gap-1', task.overdue && 'font-medium text-red-700')}><Clock className="size-3" />{formatDate(task.due_at, locale)}</span>}
            {task.checklist.length > 0 && <span className="flex items-center gap-1"><CheckSquare className="size-3" />{done}/{task.checklist.length}</span>}
            {task.comments.length > 0 && <span className="flex items-center gap-1"><MessageSquare className="size-3" />{task.comments.length}</span>}
            {task.needs_photo && <Camera className={cn('size-3', task.has_photo && 'text-emerald-600')} />}
          </div>
        </button>
      </DialogTrigger>
      <DialogContent dir={dir} className="max-w-xl">
        <DialogHeader><DialogTitle>{editing ? t('hr.tasks.edit') : task.title}</DialogTitle></DialogHeader>
        {editing ? <TaskForm task={task} branches={props.branches} employees={props.employees} priorities={props.priorities} onDone={() => setOpen(false)} /> : (
          <div className="flex flex-col gap-3 text-sm">
            <div className="flex flex-wrap gap-2">
              <Badge variant="outline">{t(`hr.task_status.${task.status}`)}</Badge>
              <Badge className={cn('border-transparent', PRIORITY[task.priority])}>{t(`hr.task_priority.${task.priority}`)}</Badge>
              {task.overdue && <Badge variant="destructive">{t('hr.tasks.overdue')}</Badge>}
            </div>
            {task.description && <p className="whitespace-pre-line text-muted-foreground">{task.description}</p>}
            {task.checklist.length > 0 && (
              <ul className="flex flex-col gap-1">
                {task.checklist.map((item, index) => <li key={index} className={cn('flex items-center gap-2', item.done && 'text-muted-foreground line-through')}><Checkbox checked={item.done} disabled />{item.text}</li>)}
              </ul>
            )}
            {task.has_photo && <a className="text-walnut underline" href={url(`/hr/tasks/${task.id}/photo`)} target="_blank" rel="noreferrer">{t('hr.tasks.photo')}</a>}
            <div className="flex flex-col gap-2 border-t pt-3">
              <span className="font-medium">{t('hr.tasks.comments')}</span>
              {task.comments.map((item) => <div key={item.id} className="rounded-md bg-muted/50 p-2"><div className="text-xs text-muted-foreground">{item.author} · {formatDate(item.created_at, locale)}</div><p className="whitespace-pre-line">{item.body}</p></div>)}
              <form className="flex gap-2" onSubmit={(event) => { event.preventDefault(); comment.post(url(`/hr/tasks/${task.id}/comments`), { preserveScroll: true, onSuccess: () => comment.reset() }); }}>
                <Input value={comment.data.body} placeholder={t('hr.tasks.comment')} onChange={(event) => comment.setData('body', event.target.value)} />
                <Button type="submit" size="sm" disabled={comment.processing || comment.data.body.trim() === ''}>{t('hr.tasks.send')}</Button>
              </form>
            </div>
            {can('hr.tasks.update') && (
              <div className="flex flex-wrap gap-2 border-t pt-3">
                {task.status === 'todo' && <Button size="sm" variant="outline" onClick={() => move('in_progress')}>{t('hr.tasks.start')}</Button>}
                {['todo', 'in_progress'].includes(task.status) && <Button size="sm" onClick={() => move('done')}>{t('hr.tasks.complete')}</Button>}
                {['done', 'cancelled'].includes(task.status) && <Button size="sm" variant="outline" onClick={() => move('todo')}>{t('hr.tasks.reopen')}</Button>}
                {['todo', 'in_progress'].includes(task.status) && <Button size="sm" variant="ghost" onClick={() => move('cancelled')}>{t('hr.tasks.cancel')}</Button>}
                <Button size="sm" variant="ghost" onClick={() => setEditing(true)}><Pencil data-icon="inline-start" />{t('common.actions.edit')}</Button>
                {can('hr.tasks.delete') && <ConfirmAction href={url(`/hr/tasks/${task.id}`)} method="delete" size="sm" variant="ghost" title={t('common.actions.remove')}><Trash2 data-icon="inline-start" />{t('common.actions.remove')}</ConfirmAction>}
              </div>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

/** The task board (columns by status) with assignment, checklists, photos and comments. */
export default function TasksIndex(props: Props) {
  const { tasks, filters: initialFilters, branches, employees, priorities } = props;
  const t = useT();
  const { dir } = useLocale();
  const { can } = useAccess();
  const [creating, setCreating] = useState(false);
  const [filters, setFilter] = useFilters(initialFilters);

  return (
    <SuiteLayout title={t('hr.tasks.title')}>
      <PageHeader
        title={t('hr.tasks.title')}
        description={t('hr.tasks.lede')}
        actions={can('hr.tasks.create') && (
          <Dialog open={creating} onOpenChange={setCreating}>
            <DialogTrigger asChild><Button size="sm"><Plus data-icon="inline-start" />{t('hr.tasks.new')}</Button></DialogTrigger>
            <DialogContent dir={dir} className="max-w-xl"><DialogHeader><DialogTitle>{t('hr.tasks.new')}</DialogTitle></DialogHeader><TaskForm branches={branches} employees={employees} priorities={priorities} onDone={() => setCreating(false)} /></DialogContent>
          </Dialog>
        )}
      />
      <FilterBar filters={filters} setFilter={setFilter} search={false} selects={[
        { key: 'assignee_id', label: t('hr.tasks.fields.assignee'), options: employees },
        { key: 'branch_id', label: t('hr.tasks.fields.branch'), options: branches },
        { key: 'priority', label: t('hr.tasks.fields.priority'), options: priorities },
        { key: 'mine', label: t('hr.tasks.mine'), options: [{ value: '1', label: t('hr.tasks.mine') }] },
      ]} />
      <div className="grid gap-4 lg:grid-cols-3">
        {COLUMNS.map((column) => {
          const rows = tasks.filter((task) => (column === 'done' ? ['done', 'cancelled'].includes(task.status) : task.status === column));

          return (
            <Card key={column} className="bg-muted/30">
              <CardContent className="flex flex-col gap-2">
                <div className="flex items-center justify-between text-sm font-semibold"><span>{t(`hr.task_status.${column}`)}</span><span className="text-muted-foreground">{rows.length}</span></div>
                {rows.length === 0 && <p className="py-4 text-center text-xs text-muted-foreground">{t('hr.tasks.empty')}</p>}
                {rows.map((task) => <TaskDetail key={task.id} task={task} props={props} />)}
              </CardContent>
            </Card>
          );
        })}
      </div>
    </SuiteLayout>
  );
}
