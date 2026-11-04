/**
 * Loose text matching for search boxes: case-insensitive, ignores Arabic diacritics and tatweel,
 * and treats أ/إ/آ as ا, ة as ه and ى as ي (the same rules the server uses for unique names).
 */
export function normalizeForSearch(text: string): string {
  return text
    .toLowerCase()
    .replace(/[ً-ْـ]/g, '')
    .replace(/[أإآ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي')
    .trim();
}

export function matches(text: string, query: string): boolean {
  const needle = normalizeForSearch(query);

  return needle === '' || normalizeForSearch(text).includes(needle);
}
