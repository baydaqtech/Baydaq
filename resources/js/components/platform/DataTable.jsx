import { router } from '@inertiajs/react';
import Paginator from '@/components/platform/Paginator';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * A server-paginated table. `rows` is a Laravel resource collection ({ data, meta, links })
 * or a plain array. Each column: { key, label, render?(row), className? }.
 * With `rowHref(row)` a click on a row opens the URL it returns (rows without one stay still).
 */
export default function DataTable({ rows, columns, rowHref, empty }) {
  const t = useT();
  const data = Array.isArray(rows) ? rows : rows.data;

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
                  {empty ?? t('admin.common.empty')}
                </TableCell>
              </TableRow>
            )}
            {data.map((row) => {
              const href = rowHref?.(row);

              return (
                <TableRow
                  key={row.id ?? row.key}
                  className={cn(href && 'cursor-pointer')}
                  onClick={href ? (event) => {
                    if (!event.target.closest('a, button, input, label')) {
                      router.visit(href);
                    }
                  } : undefined}
                >
                  {columns.map((column) => (
                    <TableCell key={column.key} className={cn('px-4 py-3', column.className)}>
                      {column.render ? column.render(row) : row[column.key]}
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
