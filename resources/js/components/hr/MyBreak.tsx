import { router } from '@inertiajs/react';
import { Coffee } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useLocale, useT } from '@/lib/i18n';

export interface BreakState {
  scheduled: { at: string; minutes: number } | null;
  current: { id: number; status: string; minutes: number; starts_at: string | null; back_at: string | null; overdue: boolean } | null;
  self_start: boolean;
}

/** The break button on /me: start (or ask for) a break, and say "I'm back". */
export default function MyBreak({ state }: { state: BreakState }) {
  const t = useT();
  const { locale } = useLocale();
  const current = state.current;
  const time = (iso: string | null) => (iso ? new Date(iso).toLocaleTimeString(locale === 'ar' ? 'ar-SA' : 'en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Riyadh' }) : '');
  const post = (url: string) => router.post(url, {}, { preserveScroll: true });

  return (
    <Card>
      <CardContent className="flex flex-wrap items-center justify-between gap-3 py-4">
        <div className="flex items-center gap-3">
          <Coffee className="size-6 text-brass" />
          <div className="text-sm">
            <div className="font-medium">{t('hr.breaks.title')}</div>
            <div className="text-muted-foreground">
              {current?.status === 'on_break' && t('hr.breaks.back_at', { time: time(current.back_at) })}
              {current?.status === 'requested' && t('hr.breaks.waiting')}
              {!current && state.scheduled && t('hr.breaks.scheduled_at', { time: state.scheduled.at, minutes: state.scheduled.minutes })}
            </div>
          </div>
        </div>
        {current ? (
          <Button size="sm" variant={current.status === 'on_break' ? 'default' : 'outline'} onClick={() => post(`/me/breaks/${current.id}/end`)}>
            {current.status === 'on_break' ? t('hr.breaks.back') : t('hr.breaks.withdraw')}
          </Button>
        ) : (
          <Button size="sm" variant="outline" onClick={() => post('/me/breaks')}>{state.self_start || state.scheduled ? t('hr.breaks.take') : t('hr.breaks.ask')}</Button>
        )}
      </CardContent>
    </Card>
  );
}
