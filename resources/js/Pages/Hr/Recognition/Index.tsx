import { useForm } from '@inertiajs/react';
import { Award, Trophy } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import type { Option } from '@/types/resources';

export interface RecognitionWall {
  items: { id: number; employee: string; badge: string; message: string; from: string | null; rewarded: boolean; at: string }[];
  top: { name: string; count: number }[];
}

/** The latest recognitions and the month's most recognised people. */
export function Wall({ wall }: { wall: RecognitionWall }) {
  const t = useT();
  const { locale } = useLocale();
  const r = (key: string, replace?: Record<string, string>) => t(`hr.recognition.${key}`, replace);

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_16rem]">
      <Card><CardContent className="divide-y py-2 text-sm">
        {wall.items.length === 0 && <p className="py-2 text-muted-foreground">{r('none')}</p>}
        {wall.items.map((item) => (
          <div key={item.id} className="flex gap-3 py-2">
            <Award className="mt-0.5 size-5 shrink-0 text-amber-500" />
            <div>
              <p><span className="font-medium">{item.employee}</span> · <Badge variant="outline">{r(`badges.${item.badge}`)}</Badge>{item.rewarded && <Badge className="ms-1">{r('rewarded')}</Badge>}</p>
              <p>{item.message}</p>
              <p className="text-xs text-muted-foreground">{item.from && `${r('from', { name: item.from })} · `}{formatDate(item.at, locale)}</p>
            </div>
          </div>
        ))}
      </CardContent></Card>
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Trophy className="size-4" />{r('top')}</CardTitle></CardHeader>
        <CardContent className="text-sm">
          <ol className="flex flex-col gap-1">{wall.top.map((person, index) => <li key={index} className="flex justify-between"><span>{index + 1}. {person.name}</span><span className="text-muted-foreground">{person.count}</span></li>)}</ol>
        </CardContent>
      </Card>
    </div>
  );
}

/** Recognition: the wall and giving thanks or a reward. */
export default function RecognitionIndex({ items, top, employees, badges }: RecognitionWall & { employees: Option[]; badges: string[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const r = (key: string) => t(`hr.recognition.${key}`);
  const form = useForm({ employee_id: employees[0]?.value ?? '', badge: 'thanks', message: '', reward: '' });

  return (
    <SuiteLayout title={r('title')}>
      <PageHeader title={r('title')} description={r('lede')} />
      {employees.length > 0 && (
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{r('give')}</CardTitle></CardHeader>
          <CardContent>
            <form className="grid gap-3 sm:grid-cols-4" onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/recognition'), { preserveScroll: true, onSuccess: () => form.reset('message', 'reward') }); }}>
              <FormField label={r('employee')} error={form.errors.employee_id}><SelectInput value={String(form.data.employee_id)} onChange={(value) => form.setData('employee_id', Number(value))} options={employees} /></FormField>
              <FormField label={r('badge')} error={form.errors.badge}><SelectInput value={form.data.badge} onChange={(value) => form.setData('badge', value)} options={badges.map((value) => ({ value, label: t(`hr.recognition.badges.${value}`) }))} /></FormField>
              <FormField label={r('message')} error={form.errors.message} className="sm:col-span-2"><Input value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} /></FormField>
              <FormField label={r('reward')} error={form.errors.reward}><Input type="number" min="0" step="0.01" dir="ltr" value={form.data.reward} onChange={(event) => form.setData('reward', event.target.value)} /></FormField>
              <div className="flex items-end sm:col-span-3"><Button type="submit" disabled={form.processing || form.data.message === ''}>{r('give')}</Button></div>
            </form>
          </CardContent>
        </Card>
      )}
      <Wall wall={{ items, top }} />
    </SuiteLayout>
  );
}
