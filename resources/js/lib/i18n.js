import { usePage } from '@inertiajs/react';

function lookup(messages, key) {
  return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), messages);
}

/**
 * Laravel-style lookup: "group.key.path", with ":name" placeholders filled from `replace`.
 * Unknown keys come back as the key itself, like Laravel's __().
 */
export function translate(messages, key, replace = {}) {
  const value = lookup(messages, key);

  if (typeof value !== 'string') {
    return key;
  }

  return Object.entries(replace).reduce((text, [name, val]) => text.replaceAll(`:${name}`, String(val)), value);
}

/**
 * Returns t(key, replace) over the shared groups (common, enums) plus any
 * page copy a controller passed as the `copy` prop.
 */
export function useT() {
  const { i18n = {}, copy = {} } = usePage().props;
  const messages = { ...i18n, ...copy };

  return (key, replace) => translate(messages, key, replace);
}

/** The current language, its direction, and links that switch to each language. */
export function useLocale() {
  const { locale, dir, localeUrls } = usePage().props;

  return { locale, dir, localeUrls };
}
