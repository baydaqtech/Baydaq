import { Card, CardContent } from '@/components/ui/card';
import { formatDate, formatNumber } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export interface LeaveBalance {
  type_id: number;
  type: string;
  entitled: string;
  taken: string;
  available: string;
  carried: string;
  accrued: string;
  adjustments: string;
  pending: string;
  available_after_pending: string;
  full_year: string;
  rate: string;
  accrual: 'yearly' | 'monthly' | 'daily';
  units: number;
  year_start: string;
  year_end: string;
  senior_from: string | null;
}

/** رصيد الإجازات with the working shown: how much has built up this leave year and what was used. */
export default function LeaveBalanceCard({ balance }: { balance: LeaveBalance }) {
  const t = useT();
  const { locale } = useLocale();
  const n = (value: string | number) => formatNumber(value, locale);
  const rows: [string, string, string][] = [
    [t('hr.leave.balance_card.carried'), '+', balance.carried],
    [t(`hr.leave.balance_card.accrued_${balance.accrual}`, { rate: n(balance.rate), units: n(balance.units) }), '+', balance.accrued],
    [t('hr.leave.balance_card.adjustments'), '+', balance.adjustments],
    [t('hr.leave.balance_card.taken'), '−', balance.taken],
  ];

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 py-4">
        <div className="flex items-baseline justify-between gap-2">
          <span className="text-sm text-muted-foreground">{balance.type}</span>
          <span className="text-xs text-muted-foreground">{formatDate(balance.year_start, locale)} → {formatDate(balance.year_end, locale)}</span>
        </div>
        <div className="text-3xl font-semibold tabular-nums">{n(balance.available)} <span className="text-sm font-normal text-muted-foreground">{t('hr.leave.balance_card.days')}</span></div>
        <dl className="flex flex-col gap-1 text-xs">
          {rows.filter(([, , value]) => Number(value) !== 0 || value === balance.accrued).map(([label, sign, value]) => (
            <div key={label} className="flex justify-between gap-3">
              <dt className="text-muted-foreground">{label}</dt>
              <dd className="tabular-nums">{sign} {n(value)}</dd>
            </div>
          ))}
          {Number(balance.pending) > 0 && (
            <div className="flex justify-between gap-3 text-amber-700"><dt>{t('hr.leave.balance_card.pending')}</dt><dd className="tabular-nums">− {n(balance.pending)} → {n(balance.available_after_pending)}</dd></div>
          )}
        </dl>
        <p className="text-xs text-muted-foreground">
          {t('hr.leave.balance_card.full_year', { days: n(balance.full_year) })}
          {balance.senior_from && ` · ${t('hr.leave.balance_card.senior_from', { date: formatDate(balance.senior_from, locale) })}`}
        </p>
      </CardContent>
    </Card>
  );
}
