import ConfirmAction from '@/components/platform/ConfirmAction';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

interface ShiftItem { id: number; date: string; starts_at: string; ends_at: string; branch: string; ramadan_hours: boolean; on_holiday: boolean }

export interface ShiftsData { mine: ShiftItem[]; open: ShiftItem[] }

/** The employee's coming shifts and open shifts they can pick up (H2). */
export default function MyShifts({ data }: { data: ShiftsData }) {
  const t = useT();
  const { locale } = useLocale();
  const line = (shift: ShiftItem) => (
    <span>
      {formatDate(shift.date, locale)} · <span dir="ltr">{shift.starts_at}–{shift.ends_at}</span> · <span className="text-muted-foreground">{shift.branch}</span>
      {shift.ramadan_hours && <Badge variant="outline" className="ms-2">{t('hr.shifts.ramadan')}</Badge>}
    </span>
  );

  if (data.mine.length === 0 && data.open.length === 0) {
    return null;
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('hr.shifts.my_shifts')}</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-4 text-sm">
        <ul className="divide-y">
          {data.mine.length === 0 && <li className="py-2 text-muted-foreground">{t('hr.shifts.none')}</li>}
          {data.mine.map((shift) => <li key={shift.id} className="py-2">{line(shift)}</li>)}
        </ul>
        {data.open.length > 0 && (
          <div>
            <h3 className="mb-1 font-medium">{t('hr.shifts.open_shifts')}</h3>
            <ul className="divide-y">
              {data.open.map((shift) => (
                <li key={shift.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  {line(shift)}
                  <ConfirmAction href={`/me/shifts/${shift.id}/claim`} method="post" size="sm" title={t('hr.shifts.pick_up')}>{t('hr.shifts.pick_up')}</ConfirmAction>
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
