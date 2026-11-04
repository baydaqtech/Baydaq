import { usePage } from '@inertiajs/react';

type Messages = Record<string, unknown>;
type Replacements = Record<string, string | number>;

function lookup(messages: Messages, key: string): unknown {
  return key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Messages)[part] : undefined), messages);
}

/**
 * Laravel-style lookup: "group.key.path", with ":name" placeholders filled from `replace`.
 * Unknown keys come back as the key itself, like Laravel's __().
 */
export function translate(messages: Messages, key: string, replace: Replacements = {}): string {
  const value = lookup(messages, key);

  if (typeof value !== 'string') {
    return key;
  }

  return Object.entries(replace).reduce((text, [name, val]) => text.replaceAll(`:${name}`, String(val)), value);
}

interface I18nProps {
  i18n?: Messages;
  copy?: Messages;
  locale: string;
  dir: 'rtl' | 'ltr';
  localeUrls: Record<string, string>;
}

function i18nProps(): I18nProps {
  return usePage().props as unknown as I18nProps;
}

/**
 * Returns t(key, replace) over the shared groups (common, enums) plus any
 * page copy a controller passed as the `copy` prop.
 */
export function useT(): (key: string, replace?: Replacements) => string {
  const { i18n = {}, copy = {} } = i18nProps();
  const messages = { ...i18n, ...copy };

  return (key, replace) => translate(messages, key, replace);
}

/** The current language, its direction, and links that switch to each language. */
export function useLocale(): { locale: string; dir: 'rtl' | 'ltr'; localeUrls: Record<string, string> } {
  const { locale, dir, localeUrls } = i18nProps();

  return { locale, dir, localeUrls };
}

/**
 * Structured page copy (arrays and objects, e.g. "landing.services"), as the controller passed it.
 */
export function useCopy<T = unknown>(path: string): T {
  const { i18n = {}, copy = {} } = i18nProps();

  return lookup({ ...i18n, ...copy }, path) as T;
}
