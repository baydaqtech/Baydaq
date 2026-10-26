import PageHeader from '@/components/platform/PageHeader';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';

interface Node {
  id: number;
  name: string;
  job: string | null;
  section: string | null;
  manager_id: number | null;
  outside: boolean;
}

function Tree({ node, nodes, depth }: { node: Node; nodes: Node[]; depth: number }) {
  const reports = nodes.filter((item) => item.manager_id === node.id);

  return (
    <li>
      <div className={`inline-flex min-w-48 flex-col rounded-lg border px-3 py-2 text-sm ${node.outside ? 'border-dashed text-muted-foreground' : 'bg-card'}`}>
        <span className="font-medium">{node.name}</span>
        {(node.job || node.section) && <span className="text-xs text-muted-foreground">{[node.job, node.section].filter(Boolean).join(' · ')}</span>}
      </div>
      {reports.length > 0 && (
        <ul className="ms-6 mt-2 flex flex-col gap-2 border-s ps-4">
          {reports.map((report) => <Tree key={report.id} node={report} nodes={nodes} depth={depth + 1} />)}
        </ul>
      )}
    </li>
  );
}

export default function OrgChart({ nodes }: { nodes: Node[] }) {
  const t = useT();
  const ids = new Set(nodes.map((node) => node.id));
  const roots = nodes.filter((node) => node.manager_id === null || !ids.has(node.manager_id));

  return (
    <SuiteLayout title={t('hr.nav.org_chart')}>
      <PageHeader title={t('hr.nav.org_chart')} />
      <Card><CardContent>
        <ul className="flex flex-col gap-3">{roots.map((root) => <Tree key={root.id} node={root} nodes={nodes} depth={0} />)}</ul>
      </CardContent></Card>
    </SuiteLayout>
  );
}
