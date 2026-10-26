import { Link } from '@inertiajs/react';
import { ExternalLink, Landmark, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import PageHeader from '@/components/platform/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useAccess, useSuite, useSuiteUrl } from '@/lib/suite';

interface Company { id: number; name: string; numbers: Record<string, string | null>; edit_path: string | null }
interface Portal { key: string; url: string; area: 'hr' | 'finance' | 'company'; number: string | null }

/** Baydaq's own tools for each government task; shown when the user's modules, features and keys allow. */
const TOOLS = [
  { key: 'qiwa', path: '/hr/gov/qiwa', module: 'hr', keys: ['hr.contracts.view'] },
  { key: 'muqeem', path: '/hr/gov/muqeem', module: 'hr', keys: ['hr.documents.view'] },
  { key: 'gosi', path: '/hr/gov/gosi', module: 'hr', keys: ['hr.employees.view_salaries'] },
  { key: 'wps', path: '/hr/payroll', module: 'hr', keys: ['hr.payroll.view'] },
  { key: 'check', path: '/hr/reports/government', module: 'hr', feature: 'hr.compliance', keys: ['hr.employees.view_salaries'] },
  { key: 'nitaqat', path: '/hr/reports/nitaqat', module: 'hr', feature: 'hr.compliance', keys: ['hr.reports.view'] },
  { key: 'documents', path: '/hr/documents/radar', module: 'hr', keys: ['hr.documents.view'] },
  { key: 'einvoicing', path: '/invoicing/units', module: 'invoicing', keys: ['invoicing.einvoicing.view', 'invoicing.einvoicing.onboard'] },
];

const NUMBER_FIELDS = ['cr_number', 'unified_number', 'vat_number', 'gosi_number', 'qiwa_id'];

export default function Gov({ companies, portals }: { companies: Company[]; portals: Portal[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const { can } = useAccess();
  const { modules, features } = useSuite();
  const g = (key: string) => t(`suite.gov.${key}`);
  const tools = TOOLS.filter((tool) => modules.includes(tool.module) && (!tool.feature || features.includes(tool.feature)) && can(...tool.keys));

  return (
    <SuiteLayout title={g('title')}>
      <PageHeader title={g('title')} description={g('lede')} />
      <div className="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
        <div className="flex flex-col gap-4">
          {companies.map((company) => (
            <Card key={company.id}>
              <CardHeader><CardTitle className="flex items-center justify-between gap-2">{company.name}{company.edit_path && <Button asChild size="sm" variant="ghost"><Link href={url(company.edit_path)}>{g('edit')}</Link></Button>}</CardTitle></CardHeader>
              <CardContent>
                <dl className="divide-y text-sm">
                  {NUMBER_FIELDS.map((field) => (
                    <div key={field} className="flex items-center justify-between gap-3 py-1.5">
                      <dt className="text-muted-foreground">{t(`suite.structure.fields.${field}`)}</dt>
                      <dd>
                        {company.numbers[field]
                          ? <button type="button" dir="ltr" className="font-mono hover:underline" title={g('copy')} onClick={() => { void navigator.clipboard?.writeText(company.numbers[field] ?? ''); toast.success(g('copied')); }}>{company.numbers[field]}</button>
                          : <span className="text-xs text-muted-foreground">{g('missing')}</span>}
                      </dd>
                    </div>
                  ))}
                </dl>
              </CardContent>
            </Card>
          ))}
          {tools.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="flex items-center gap-2"><Wrench className="size-4" />{g('tools')}</CardTitle></CardHeader>
              <CardContent className="flex flex-col gap-1">
                {tools.map((tool) => <Link key={tool.key} href={url(tool.path)} className="rounded-md px-2 py-1.5 text-sm hover:bg-muted">{g(`tool.${tool.key}`)}</Link>)}
              </CardContent>
            </Card>
          )}
        </div>
        <div className="flex flex-col gap-4">
          {(['hr', 'finance', 'company'] as const).map((area) => (
            <Card key={area}>
              <CardHeader><CardTitle className="flex items-center gap-2"><Landmark className="size-4" />{g(`areas.${area}`)}</CardTitle></CardHeader>
              <CardContent className="grid gap-2 sm:grid-cols-2">
                {portals.filter((portal) => portal.area === area).map((portal) => (
                  <a key={portal.key} href={portal.url} target="_blank" rel="noreferrer" className="flex flex-col gap-1 rounded-lg border p-3 transition-colors hover:bg-muted/50">
                    <span className="flex items-center justify-between gap-2 font-medium">{g(`portal.${portal.key}.name`)}<ExternalLink className="size-3.5 text-muted-foreground" /></span>
                    <span className="text-xs text-muted-foreground">{g(`portal.${portal.key}.hint`)}</span>
                    {portal.number && <Badge variant="outline" className="self-start">{t(`suite.structure.fields.${portal.number}`)}</Badge>}
                  </a>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </SuiteLayout>
  );
}
