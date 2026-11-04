import { useEffect, useState } from 'react';

export interface ClientTheme {
  app_name: string | null;
  logo_url: string | null;
  primary: string | null;
  primary_foreground: string | null;
  accent: string | null;
  radius: string;
  mode: 'light' | 'dark' | 'system';
}

export type ColorMode = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'baydaq.mode';
const VARS = ['--primary', '--primary-foreground', '--ring', '--sidebar-primary', '--chart-1', '--radius'];

function storedMode(): ColorMode | null {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY);
    return value === 'light' || value === 'dark' || value === 'system' ? value : null;
  } catch {
    return null;
  }
}

function applyMode(mode: ColorMode): void {
  const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
}

/**
 * Puts the client's theme (colours, roundness) on the page and the light/dark mode: the
 * person's own choice first, else the client's default.
 */
export function useClientTheme(theme: ClientTheme | null | undefined): void {
  useEffect(() => {
    const root = document.documentElement;
    VARS.forEach((name) => root.style.removeProperty(name));

    if (theme?.primary) {
      root.style.setProperty('--primary', theme.primary);
      root.style.setProperty('--primary-foreground', theme.primary_foreground ?? '#FFFFFF');
    }

    if (theme?.accent) {
      ['--ring', '--sidebar-primary', '--chart-1'].forEach((name) => root.style.setProperty(name, theme.accent as string));
    }

    if (theme?.radius) {
      root.style.setProperty('--radius', theme.radius);
    }

    applyMode(storedMode() ?? theme?.mode ?? 'light');
  }, [theme?.primary, theme?.primary_foreground, theme?.accent, theme?.radius, theme?.mode]);
}

/** The person's light/dark choice, remembered on this device. */
export function useColorMode(fallback: ColorMode = 'light'): [ColorMode, (mode: ColorMode) => void] {
  const [mode, setMode] = useState<ColorMode>(() => (typeof window === 'undefined' ? fallback : storedMode() ?? fallback));

  return [mode, (next: ColorMode) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Private browsing: the choice lasts for this page only.
    }
    setMode(next);
    applyMode(next);
  }];
}
