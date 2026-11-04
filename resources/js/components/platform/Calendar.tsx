import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { addDays, addMonths, formatDay, parseIsoDate, sameDay, startOfMonth, toIsoDate, todayIso, withinRange } from '@/lib/dates';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type View = 'days' | 'months' | 'years';

interface Props {
  /** "YYYY-MM-DD" (day mode) or "YYYY-MM" (month mode). */
  value?: string | null;
  onSelect: (value: string) => void;
  mode?: 'day' | 'month';
  min?: string | null;
  max?: string | null;
}

const WEEK_STARTS_ON = 0; // Sunday, as the Saudi work week

/**
 * Baydaq calendar: a month of days, a year of months and a dozen years, all keyboard-driven.
 * Arrow keys move by day (left/right follow the reading direction), up/down by week,
 * PageUp/PageDown by month (with Shift by year), Home/End to the week's ends, Enter picks.
 */
export default function Calendar({ value, onSelect, mode = 'day', min, max }: Props) {
  const t = useT();
  const { locale, dir } = useLocale();
  const selected = parseIsoDate(mode === 'month' && value ? `${value.slice(0, 7)}-01` : value);
  const today = parseIsoDate(todayIso())!;
  const [focused, setFocused] = useState<Date>(selected ?? today);
  const [view, setView] = useState<View>(mode === 'month' ? 'months' : 'days');
  const grid = useRef<HTMLDivElement>(null);
  const keyboard = useRef(false);

  useEffect(() => {
    if (keyboard.current) {
      grid.current?.querySelector<HTMLButtonElement>('[data-focused="true"]')?.focus();
      keyboard.current = false;
    }
  }, [focused, view]);

  const month = startOfMonth(focused);
  const weekdays = useMemo(() => {
    const sunday = new Date(Date.UTC(2026, 9, 25)); // a Sunday
    return Array.from({ length: 7 }, (_, index) => formatDay(addDays(sunday, (index + WEEK_STARTS_ON) % 7), locale, { weekday: 'short' }));
  }, [locale]);
  const days = useMemo(() => {
    const offset = (month.getUTCDay() - WEEK_STARTS_ON + 7) % 7;
    return Array.from({ length: 42 }, (_, index) => addDays(month, index - offset));
  }, [month]);

  const pick = (date: Date) => {
    if (withinRange(date, min, max)) {
      onSelect(toIsoDate(date));
    }
  };

  function moveTo(date: Date) {
    keyboard.current = true;
    setFocused(date);
  }

  function onDaysKey(event: KeyboardEvent) {
    const forward = dir === 'rtl' ? -1 : 1;
    const moves: Record<string, () => Date> = {
      ArrowRight: () => addDays(focused, forward),
      ArrowLeft: () => addDays(focused, -forward),
      ArrowDown: () => addDays(focused, 7),
      ArrowUp: () => addDays(focused, -7),
      PageDown: () => addMonths(focused, event.shiftKey ? 12 : 1),
      PageUp: () => addMonths(focused, event.shiftKey ? -12 : -1),
      Home: () => addDays(focused, -((focused.getUTCDay() - WEEK_STARTS_ON + 7) % 7)),
      End: () => addDays(focused, 6 - ((focused.getUTCDay() - WEEK_STARTS_ON + 7) % 7)),
    };

    if (moves[event.key]) {
      event.preventDefault();
      moveTo(moves[event.key]());
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      pick(focused);
    }
  }

  const Prev = dir === 'rtl' ? ChevronRight : ChevronLeft;
  const Next = dir === 'rtl' ? ChevronLeft : ChevronRight;
  const year = focused.getUTCFullYear();
  const decadeStart = year - (year % 12);

  const header = (title: string, onTitle: (() => void) | null, step: (direction: 1 | -1) => void, labels: [string, string]) => (
    <div className="mb-2 flex items-center justify-between gap-1">
      <Button type="button" variant="ghost" size="icon-sm" onClick={() => step(-1)} aria-label={labels[0]}><Prev /></Button>
      {onTitle
        ? <Button type="button" variant="ghost" size="sm" className="font-semibold" onClick={onTitle} aria-label={t('common.ui.choose_year')}>{title}</Button>
        : <span className="text-sm font-semibold">{title}</span>}
      <Button type="button" variant="ghost" size="icon-sm" onClick={() => step(1)} aria-label={labels[1]}><Next /></Button>
    </div>
  );

  if (view === 'years') {
    return (
      <div ref={grid} className="w-64">
        {header(`${formatDay(new Date(Date.UTC(decadeStart, 0, 1)), locale, { year: 'numeric' })} – ${formatDay(new Date(Date.UTC(decadeStart + 11, 0, 1)), locale, { year: 'numeric' })}`, null,
          (direction) => setFocused(addMonths(focused, direction * 144)), [t('common.ui.previous_years'), t('common.ui.next_years')])}
        <div className="grid grid-cols-3 gap-1">
          {Array.from({ length: 12 }, (_, index) => decadeStart + index).map((item) => (
            <Button key={item} type="button" size="sm" variant={item === year ? 'default' : 'ghost'} className="tabular-nums" onClick={() => { setFocused(new Date(Date.UTC(item, focused.getUTCMonth(), 1))); setView('months'); }}>
              {formatDay(new Date(Date.UTC(item, 0, 1)), locale, { year: 'numeric' })}
            </Button>
          ))}
        </div>
      </div>
    );
  }

  if (view === 'months') {
    const chooseMonth = (item: Date) => {
      if (mode === 'month') {
        onSelect(toIsoDate(item).slice(0, 7));
        return;
      }
      setFocused(item);
      setView('days');
    };

    return (
      <div ref={grid} className="w-64">
        {header(formatDay(focused, locale, { year: 'numeric' }), () => setView('years'), (direction) => setFocused(addMonths(focused, direction * 12)), [t('common.ui.previous_year'), t('common.ui.next_year')])}
        <div className="grid grid-cols-3 gap-1">
          {Array.from({ length: 12 }, (_, index) => new Date(Date.UTC(year, index, 1))).map((item) => {
            const lastDay = new Date(Date.UTC(item.getUTCFullYear(), item.getUTCMonth() + 1, 0));
            const allowed = withinRange(lastDay, min, null) && withinRange(item, null, max);
            const isSelected = selected !== null && selected.getUTCFullYear() === year && selected.getUTCMonth() === item.getUTCMonth();

            return (
              <Button
                key={item.getUTCMonth()} type="button" size="sm" disabled={!allowed} variant={isSelected ? 'default' : 'ghost'}
                onClick={() => chooseMonth(item)}
              >
                {formatDay(item, locale, { month: 'short' })}
              </Button>
            );
          })}
        </div>
      </div>
    );
  }

  return (
    <div className="w-64">
      {header(formatDay(month, locale, { month: 'long', year: 'numeric' }), () => setView('months'), (direction) => setFocused(addMonths(focused, direction)), [t('common.ui.previous_month'), t('common.ui.next_month')])}
      <div role="grid" ref={grid} onKeyDown={onDaysKey} className="grid grid-cols-7 gap-0.5 text-center">
        {weekdays.map((weekday) => <span key={weekday} role="columnheader" className="pb-1 text-xs font-medium text-muted-foreground">{weekday}</span>)}
        {days.map((day) => {
          const outside = day.getUTCMonth() !== month.getUTCMonth();
          const allowed = withinRange(day, min, max);
          const isSelected = sameDay(day, selected);
          const isFocused = sameDay(day, focused);

          return (
            <button
              key={day.getTime()}
              type="button"
              role="gridcell"
              tabIndex={isFocused ? 0 : -1}
              data-focused={isFocused}
              disabled={!allowed}
              aria-selected={isSelected}
              aria-label={formatDay(day, locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
              onClick={() => pick(day)}
              onFocus={() => setFocused(day)}
              className={cn(
                'flex size-8 items-center justify-center rounded-md text-sm tabular-nums transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-30',
                outside && 'text-muted-foreground/60',
                sameDay(day, today) && !isSelected && 'font-semibold text-primary ring-1 ring-primary/30',
                isSelected && 'bg-primary text-primary-foreground hover:bg-primary/90',
              )}
            >
              {formatDay(day, locale, { day: 'numeric' })}
            </button>
          );
        })}
      </div>
    </div>
  );
}
