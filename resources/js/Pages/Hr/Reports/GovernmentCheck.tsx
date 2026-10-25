import { Link, useForm } from '@inertiajs/react';
import FormField from '@/components/platform/FormField';
import PageHeader from '@/components/platform/PageHeader';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SuiteLayout from '@/layouts/SuiteLayout';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface Result {
  source: string;
  matched: number;
  mismatched: { id: number; name: string; ours: string; theirs: string; difference: string }[];
  missing_here: { national_id: string; wage: string }[];
  missing_there: { id: number; name: string; ours: string }[];
}

/** Baydaq's wages against a GOSI / Qiwa / Mudad export (H5). */
export default function GovernmentCheck({ result, sources }: { result: Result | null; sources: string[] }) {
  const t = useT();
  const { locale } = useLocale();
  const url = useSuiteUrl();
  const c = (key: string, replace?: Record<string, string>) => t(`hr.compliance.${key}`, replace);
  const form = useForm<{ source: string; file: File | null }>({ source: result?.source ?? sources[0], file: null });
  const money = (value: string) => formatMoney(value, locale);

  return (
    <SuiteLayout title={c('government')}>
      <PageHeader title={c('government')} description={c('government_hint')} />
      <Card className="mb-4"><CardContent>
        <form onSubmit={(event) => { event.preventDefault(); form.post(url('/hr/reports/government'), { forceFormData: true }); }} className="grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
          <FormField label={c('source')}><SelectInput value={form.data.source} onChange={(value) => form.setData('source', value)} options={sources.map((source) => ({ value: source, label: c(`sources.${source}`) }))} /></FormField>
          <FormField label={c('file')} error={form.errors.file}><Input type="file" accept=".csv,text/csv" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></FormField>
          <Button type="submit" disabled={form.processing || !form.data.file}>{c('check')}</Button>
        </form>
      </CardContent></Card>
      {result && (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">{c('matched', { count: String(result.matched) })}</p>
          {result.mismatched.length + result.missing_here.length + result.missing_there.length === 0 && <p className="text-emerald-700">{c('all_good')}</p>}
          {result.mismatched.length > 0 && (
            <Card className="border-red-200">
              <CardHeader><CardTitle className="text-base">{c('mismatched')} ({result.mismatched.length})</CardTitle></CardHeader>
              <CardContent>
                <ul className="divide-y text-sm">
                  {result.mismatched.map((row) => (
                    <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                      <Link href={url(`/hr/employees/${row.id}`)} className="font-medium hover:underline">{row.name}</Link>
                      <span>{c('ours')}: {money(row.ours)} · {c('theirs')}: {money(row.theirs)} · <strong>{c('difference')}: <span dir="ltr">{money(row.difference)}</span></strong></span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
          {result.missing_here.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="text-base">{c('missing_here')} ({result.missing_here.length})</CardTitle></CardHeader>
              <CardContent><ul className="flex flex-wrap gap-3 text-sm">{result.missing_here.map((row) => <li key={row.national_id + row.wage} dir="ltr">{row.national_id} · {money(row.wage)}</li>)}</ul></CardContent>
            </Card>
          )}
          {result.missing_there.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="text-base">{c('missing_there')} ({result.missing_there.length})</CardTitle></CardHeader>
              <CardContent><ul className="flex flex-wrap gap-3 text-sm">{result.missing_there.map((row) => <li key={row.id}><Link href={url(`/hr/employees/${row.id}`)} className="hover:underline">{row.name}</Link> · {money(row.ours)}</li>)}</ul></CardContent>
            </Card>
          )}
        </div>
      )}
    </SuiteLayout>
  );
}
