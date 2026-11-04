/** Number and money formatting in the page language (Arabic digits in Arabic). */
function numberLocale(locale: string): string {
  return locale === 'ar' ? 'ar-SA' : 'en-SA';
}

type Numeric = number | string | null | undefined;

export function formatMoney(amount: Numeric, locale: string, currency = 'SAR'): string {
  if (amount === null || amount === undefined || amount === '') {
    return '';
  }

  return new Intl.NumberFormat(numberLocale(locale), { style: 'currency', currency }).format(Number(amount));
}

export function formatNumber(value: Numeric, locale: string): string {
  return new Intl.NumberFormat(numberLocale(locale)).format(Number(value));
}

export function formatDate(value: string | Date | null | undefined, locale: string): string {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB', { dateStyle: 'medium' }).format(new Date(value));
}

/** 60 → "60%" / "٦٠٪". */
export function formatPercent(value: Numeric, locale: string): string {
  return new Intl.NumberFormat(numberLocale(locale), { style: 'percent', maximumFractionDigits: 2 }).format(Number(value) / 100);
}
