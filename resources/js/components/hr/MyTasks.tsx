import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Camera, Clock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface MyTask {
  id: number; title: string; description: string | null; status: string; priority: string; branch: string | null;
  checklist: { text: string; done: boolean }[]; needs_photo: boolean; has_photo: boolean; due_at: string | null; overdue: boolean;
}

function TaskItem({ task }: { task: MyTask }) {
  const t = useT();
  const { locale } = useLocale();
  const [checked, setChecked] = useState<number[]>(task.checklist.flatMap((item, index) => (item.done ? [index] : [])));
  const form = useForm<{ status: string; checked: number[]; photo: File | null; note: string }>({ status: '', checked, photo: null, note: '' });

  function send(status: string) {
    form.transform((data) => ({ ...data, status, checked }));
    form.post(`/me/tasks/${task.id}/status`, { preserveScroll: true, forceFormData: true });
  }

  const errors = form.errors as Record<string, string>;

  return (
    <li className={cn('flex flex-col gap-2 rounded-md border p-3', task.overdue && 'border-red-300')}>
      <div className="flex items-start justify-between gap-2">
        <span className="font-medium">{task.title}</span>
        <Badge variant="outline">{t(`hr.task_status.${task.status}`)}</Badge>
      </div>
      {task.description && <p className="whitespace-pre-line text-sm text-muted-foreground">{task.description}</p>}
      {task.due_at && <p className={cn('flex items-center gap-1 text-xs', task.overdue ? 'text-red-700' : 'text-muted-foreground')}><Clock className="size-3" />{formatDate(task.due_at, locale)}</p>}
      {task.checklist.length > 0 && (
        <ul className="flex flex-col gap-1 text-sm">
          {task.checklist.map((item, index) => (
            <li key={index}><label className="flex items-center gap-2"><Checkbox checked={checked.includes(index)} onCheckedChange={(state) => setChecked(state === true ? [...checked, index] : checked.filter((i) => i !== index))} />{item.text}</label></li>
          ))}
        </ul>
      )}
      {task.needs_photo && (
        <label className="flex items-center gap-2 text-sm"><Camera className="size-4" /><Input type="file" accept="image/*" capture="environment" onChange={(event) => form.setData('photo', event.target.files?.[0] ?? null)} /></label>
      )}
      {Object.values(errors)[0] && <p className="text-sm text-destructive">{Object.values(errors)[0]}</p>}
      <div className="flex gap-2">
        {task.status === 'todo' && <Button size="sm" variant="outline" disabled={form.processing} onClick={() => send('in_progress')}>{t('hr.tasks.start')}</Button>}
        <Button size="sm" disabled={form.processing} onClick={() => send('done')}>{t('hr.tasks.complete')}</Button>
      </div>
    </li>
  );
}

/** My open tasks on /me (the app shows the same list through the API). */
export default function MyTasks({ tasks }: { tasks: MyTask[] }) {
  const t = useT();

  if (tasks.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.tasks.title')}</CardTitle></CardHeader>
      <CardContent><ul className="flex flex-col gap-3">{tasks.map((task) => <TaskItem key={task.id} task={task} />)}</ul></CardContent>
    </Card>
  );
}
