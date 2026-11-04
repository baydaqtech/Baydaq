import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { useT } from '@/lib/i18n';
import { useSuite } from '@/lib/suite';
import type { Option } from '@/types/resources';

export type ScopeLevel = 'client' | 'organization' | 'company' | 'branch';

interface Props {
  level: ScopeLevel | '';
  nodeId: number | '';
  onChange: (level: ScopeLevel | '', nodeId: number | '') => void;
  levels: Option[];
  errors?: { level?: string; node_id?: string };
}

/**
 * Pick where a role applies: the whole account, an organization, a company or a branch,
 * from the tree the current user can reach.
 */
export default function ScopePicker({ level, nodeId, onChange, levels, errors = {} }: Props) {
  const t = useT();
  const { tree, is_owner } = useSuite();

  const companies = tree.flatMap((organization) => organization.companies.map((company) => ({ ...company, organization: organization.name })));
  const branches = companies.flatMap((company) => company.branches.map((branch) => ({ ...branch, company: company.name })));

  const nodes: Option[] = level === 'organization'
    ? tree.map((organization) => ({ value: organization.id, label: organization.name }))
    : level === 'company'
      ? companies.map((company) => ({ value: company.id, label: `${company.name} — ${company.organization}` }))
      : level === 'branch'
        ? branches.map((branch) => ({ value: branch.id, label: `${branch.name} — ${branch.company}` }))
        : [];

  const offeredLevels = levels.filter((option) => is_owner || option.value !== 'client');

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <FormField label={t('suite.access.fields.level')} htmlFor="level" error={errors.level}>
        <SelectInput id="level" value={level} onChange={(next) => onChange(next as ScopeLevel, '')} options={offeredLevels} />
      </FormField>
      {level && level !== 'client' && (
        <FormField label={t('suite.access.fields.node')} htmlFor="node_id" error={errors.node_id}>
          <SelectInput id="node_id" value={nodeId} onChange={(next) => onChange(level, Number(next))} options={nodes} />
        </FormField>
      )}
    </div>
  );
}
