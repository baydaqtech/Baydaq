/**
 * Calendar days as plain values: "YYYY-MM-DD" strings in forms and the API, UTC-midnight Date
 * objects inside the pickers, so a day never shifts with the browser's time zone. Gregorian is
 * the working calendar; Hijri (Umm al-Qura) is shown alongside.
 */

export type IsoDate = string;

const ISO_DAY = /^(\d{4})-(\d{2})-(\d{2})/;

export function parseIsoDate(value?: string | null): Date | null {
  const match = value ? ISO_DAY.exec(value) : null;

  return match ? new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))) : null;
}

export function toIsoDate(date: Date): IsoDate {
  return date.toISOString().slice(0, 10);
}

export function todayIso(): IsoDate {
  const now = new Date();

  return toIsoDate(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())));
}

export function addDays(date: Date, days: number): Date {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate() + days));
}

/** Same day in another month, clamped to that month's length (31 Jan + 1 month → 28/29 Feb). */
export function addMonths(date: Date, months: number): Date {
  const target = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + months, 1));
  const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();

  return new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth(), Math.min(date.getUTCDate(), last)));
}

export function startOfMonth(date: Date): Date {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
}

export function sameDay(a: Date | null, b: Date | null): boolean {
  return Boolean(a && b && a.getTime() === b.getTime());
}

/** Whether a day falls inside optional "YYYY-MM-DD" bounds. */
export function withinRange(date: Date, min?: string | null, max?: string | null): boolean {
  const lower = parseIsoDate(min);
  const upper = parseIsoDate(max);

  return (!lower || date >= lower) && (!upper || date <= upper);
}

/** Intl tag for Gregorian dates in the page language (Arabic digits in Arabic). */
export function dateLocale(locale: string): string {
  return locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB';
}

export function hijriLocale(locale: string): string {
  return locale === 'ar' ? 'ar-SA-u-ca-islamic-umalqura' : 'en-SA-u-ca-islamic-umalqura';
}

export function formatDay(date: Date, locale: string, options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }): string {
  return new Intl.DateTimeFormat(dateLocale(locale), { ...options, timeZone: 'UTC' }).format(date);
}

export function formatHijri(date: Date, locale: string): string {
  return new Intl.DateTimeFormat(hijriLocale(locale), { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
}

/** Two-digit hours and minutes: "07:05". */
export function pad(value: number): string {
  return String(value).padStart(2, '0');
}
