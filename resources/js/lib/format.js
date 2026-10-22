/** Number and money formatting in the page language (Arabic digits in Arabic). */
function numberLocale(locale) {
  return locale === 'ar' ? 'ar-SA' : 'en-SA';
}

export function formatMoney(amount, locale, currency = 'SAR') {
  if (amount === null || amount === undefined || amount === '') {
    return '';
  }

  return new Intl.NumberFormat(numberLocale(locale), { style: 'currency', currency }).format(Number(amount));
}

export function formatNumber(value, locale) {
  return new Intl.NumberFormat(numberLocale(locale)).format(Number(value));
}

export function formatDate(value, locale) {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-GB', { dateStyle: 'medium' }).format(new Date(value));
}
