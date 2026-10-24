import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Paginator from '@/components/platform/Paginator';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/resources';

export interface Column<T> {
  key: string;
  label: ReactNode;
  render?: (row: T) => ReactNode;
  className?: string;
}

interface Props<T> {
  rows: Paginated<T> | T[];
  columns: Column<T>[];
  rowHref?: (row: T) => string | undefined;
  rowKey?: (row: T) => string | number;
  empty?: ReactNode;
}

/**
 * A server-paginated table. `rows` is a Laravel resource collection ({ data, meta, links })
 * or a plain array. Each column: { key, label, render?(row), className? }.
 * With `rowHref(row)` a click on a row opens the URL it returns (rows without one stay still).
 */
export default function DataTable<T extends Record<string, unknown>>({ rows, columns, rowHref, rowKey, empty }: Props<T>) {
  const t = useT();
  const data = Array.isArray(rows) ? rows : rows.data;
  const keyOf = rowKey ?? ((row: T) => String(row.id ?? row.key));

  return (
    <Card className="gap-0 overflow-hidden py-0">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/50 hover:bg-muted/50">
              {columns.map((column) => (
                <TableHead key={column.key} className={cn('h-10 px-4 text-start', column.className)}>{column.label}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.length === 0 && (
              <TableRow>
                <TableCell colSpan={columns.length} className="px-4 py-10 text-center text-muted-foreground">
                  {empty ?? t('common.actions.empty')}
                </TableCell>
              </TableRow>
            )}
            {data.map((row) => {
              const href = rowHref?.(row);

              return (
                <TableRow
                  key={keyOf(row)}
                  className={cn(href && 'cursor-pointer')}
                  onClick={href ? (event) => {
                    if (!(event.target as HTMLElement).closest('a, button, input, label')) {
                      router.visit(href);
                    }
                  } : undefined}
                >
                  {columns.map((column) => (
                    <TableCell key={column.key} className={cn('px-4 py-3', column.className)}>
                      {column.render ? column.render(row) : (row[column.key] as ReactNode)}
                    </TableCell>
                  ))}
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>
      {!Array.isArray(rows) && <Paginator meta={rows.meta} links={rows.links} />}
    </Card>
  );
}
