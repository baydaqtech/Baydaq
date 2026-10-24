import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { FeatureMatrix } from '@/types/suite';

interface Props {
  matrix: FeatureMatrix;
  value: string[];
  onChange: (keys: string[]) => void;
  /** Keys the current user may hand out; the rest render disabled (no escalation). */
  grantable: string[];
  isOwner: boolean;
}

const ACTIONS = ['view', 'create', 'update', 'delete', 'export', 'approve'];

/**
 * Features as rows, standard actions as columns, extra abilities as chips under the row.
 */
export default function PermissionMatrix({ matrix, value, onChange, grantable, isOwner }: Props) {
  const t = useT();
  const has = (key: string) => value.includes(key);
  const may = (key: string) => isOwner || grantable.includes(key);

  function toggle(key: string, on: boolean) {
    onChange(on ? [...new Set([...value, key])] : value.filter((item) => item !== key));
  }

  return (
    <div className="overflow-x-auto rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow className="bg-muted/50 hover:bg-muted/50">
            <TableHead className="px-4 text-start">{t('suite.access.permissions')}</TableHead>
            {ACTIONS.map((action) => (
              <TableHead key={action} className="w-20 px-2 text-center">{t(`suite.feature_action.${action}`)}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {Object.entries(matrix).map(([group, features]) => (
            <>
              <TableRow key={`${group}-head`} className="bg-muted/30 hover:bg-muted/30">
                <TableCell colSpan={ACTIONS.length + 1} className="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t(group === 'suite' ? 'suite.title' : `suite.module.${group}`)}
                </TableCell>
              </TableRow>
              {features.map((feature) => (
                <TableRow key={`${group}.${feature.feature}`}>
                  <TableCell className="px-4 py-3 align-top">
                    <div className="font-medium">{t(`${feature.label_file}.features.${feature.feature}`)}</div>
                    {feature.abilities.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1.5">
                        {feature.abilities.map((ability) => {
                          const key = `${group}.${feature.feature}.${ability}`;
                          const checked = has(key);

                          return (
                            <button
                              key={key}
                              type="button"
                              disabled={!may(key)}
                              aria-pressed={checked}
                              onClick={() => toggle(key, !checked)}
                              className={cn(
                                'rounded-full border px-2.5 py-0.5 text-xs transition-colors disabled:opacity-40',
                                checked ? 'border-brass bg-brass/15 text-walnut' : 'border-border text-muted-foreground hover:border-brass/60',
                              )}
                            >
                              {t(`${feature.label_file}.abilities.${ability}`)}
                            </button>
                          );
                        })}
                      </div>
                    )}
                  </TableCell>
                  {ACTIONS.map((action) => {
                    const key = `${group}.${feature.feature}.${action}`;
                    const offered = feature.actions.includes(action);

                    return (
                      <TableCell key={action} className="px-2 py-3 text-center align-top">
                        {offered && (
                          <Checkbox
                            checked={has(key)}
                            disabled={!may(key)}
                            onCheckedChange={(state) => toggle(key, state === true)}
                            aria-label={`${t(`${feature.label_file}.features.${feature.feature}`)}: ${t(`suite.feature_action.${action}`)}`}
                          />
                        )}
                      </TableCell>
                    );
                  })}
                </TableRow>
              ))}
            </>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
