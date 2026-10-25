import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Building2, Contact, CornerDownLeft, History, MapPin, Pin, Search, Star, UserRound, X } from 'lucide-react';
import { NAV } from '@/components/suite/SuiteNav';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useLocale, useT } from '@/lib/i18n';
import { useAccess, useSuite, useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

interface Item {
  key: string;
  group: string;
  label: string;
  detail?: string | null;
  icon: LucideIcon;
  href: string;
  onRemove?: () => void;
}

interface Hit { type: string; label: string; detail: string | null; path: string }

const HIT_ICONS: Record<string, LucideIcon> = { employee: UserRound, member: UserRound, customer: Contact };

/** Lowercase, without Arabic diacritics or tatweel, so "محمد" matches "مُحَمَّد". */
function normalize(text: string): string {
  return text.toLowerCase().replace(/[ً-ٟـ]/g, '');
}

/**
 * ⌘K / Ctrl+K: jump to any page, company, branch, saved or recent scope, or record (employee,
 * member) the user may see (H1). Records come from the server; everything else is filtered here.
 */
export default function CommandPalette() {
  const t = useT();
  const { dir } = useLocale();
  const { can } = useAccess();
  const { tree, modules, shortcuts, scope, ai } = useSuite();
  const url = useSuiteUrl();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<Hit[]>([]);
  const [active, setActive] = useState(0);
  const listRef = useRef<HTMLUListElement>(null);
  const save = useForm({ name: '' });
  const path = typeof window === 'undefined' ? '/' : window.location.pathname.replace(/^\/suite\/[^/]+/, '') || '/';

  useEffect(() => {
    function onKey(event: globalThis.KeyboardEvent) {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setOpen((value) => !value);
      }
    }

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => {
    if (!open || query.trim().length < 2) {
      setHits([]);
      return;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      fetch(`${url('/search', scope.segment, scope.branches).split('?')[0]}?q=${encodeURIComponent(query.trim())}${scope.branches.length ? `&branches=${scope.branches.join(',')}` : ''}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: controller.signal,
      }).then((response) => (response.ok ? response.json() : { hits: [] })).then((data) => setHits(data.hits ?? [])).catch(() => undefined);
    }, 200);

    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [open, query, scope.segment, scope.branches, url]);

  const items = useMemo<Item[]>(() => {
    const term = normalize(query.trim());
    const matches = (...texts: (string | null | undefined)[]) => term === '' || texts.some((text) => text && normalize(text).includes(term));

    const pages: Item[] = NAV
      .filter((item) => (!item.module || modules.includes(item.module)) && (!('ai' in item) || ai) && (!item.keys || can(...item.keys)))
      .map((item) => ({ key: `page:${item.path}`, group: 'pages', label: t(item.label), icon: item.icon, href: url(item.path) }))
      .filter((item) => matches(item.label));

    const saved: Item[] = shortcuts
      .filter((shortcut) => shortcut.pinned || term === '')
      .map((shortcut) => ({
        key: `shortcut:${shortcut.id}`,
        group: shortcut.pinned ? 'saved' : 'recent',
        label: shortcut.name ?? shortcut.label,
        detail: shortcut.name ? shortcut.label : null,
        icon: shortcut.pinned ? Star : History,
        href: url(path, shortcut.segment, shortcut.branches),
        onRemove: shortcut.pinned ? () => router.delete(url(`/shortcuts/${shortcut.id}`), { preserveScroll: true }) : undefined,
      }))
      .filter((item) => item.group === 'recent' || matches(item.label, item.detail));

    const places: Item[] = term === '' ? [] : tree.flatMap((organization) => organization.companies.flatMap((company) => [
      { key: `company:${company.slug}`, group: 'places', label: company.name, detail: organization.name, icon: Building2, href: url(path, `c-${company.slug}`, []) },
      ...company.branches.map((branch) => ({ key: `branch:${branch.id}`, group: 'places', label: branch.name, detail: company.name, icon: MapPin, href: url(path, `c-${company.slug}`, [branch.id]) })),
    ])).filter((item) => matches(item.label, item.detail)).slice(0, 8);

    const records: Item[] = hits.map((hit) => ({ key: `hit:${hit.path}`, group: 'records', label: hit.label, detail: hit.detail, icon: HIT_ICONS[hit.type] ?? Search, href: url(hit.path) }));

    return [...saved, ...records, ...places, ...pages.slice(0, term === '' ? 6 : 10)];
  }, [query, hits, tree, modules, shortcuts, can, t, url, path]);

  useEffect(() => setActive(0), [query, hits]);

  function go(item: Item | undefined) {
    if (!item) {
      return;
    }

    setOpen(false);
    setQuery('');
    router.visit(item.href);
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = (active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % Math.max(1, items.length);
      setActive(next);
      listRef.current?.querySelector(`[data-index="${next}"]`)?.scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter') {
      event.preventDefault();
      go(items[active]);
    }
  }

  function saveScope() {
    save.post(url('/shortcuts'), { preserveScroll: true, onSuccess: () => save.reset() });
  }

  let lastGroup = '';

  return (
    <>
      <Button variant="outline" size="sm" className="hidden gap-2 text-muted-foreground sm:inline-flex" onClick={() => setOpen(true)}>
        <Search className="size-4" />
        <span>{t('suite.palette.open')}</span>
        <kbd className="rounded border bg-muted px-1 text-[10px]" dir="ltr">⌘K</kbd>
      </Button>
      <Button variant="ghost" size="icon" className="sm:hidden" onClick={() => setOpen(true)} aria-label={t('suite.palette.open')}><Search /></Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent dir={dir} className="gap-0 overflow-hidden p-0 sm:max-w-xl" showCloseButton={false}>
          <DialogTitle className="sr-only">{t('suite.palette.open')}</DialogTitle>
          <div className="flex items-center gap-2 border-b px-3">
            <Search className="size-4 text-muted-foreground" />
            <Input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={onKeyDown} placeholder={t('suite.palette.placeholder')} className="h-12 border-0 shadow-none focus-visible:ring-0" />
          </div>
          <ul ref={listRef} className="max-h-[60vh] overflow-y-auto p-2" role="listbox">
            {items.length === 0 && <li className="px-3 py-6 text-center text-sm text-muted-foreground">{t('suite.palette.empty')}</li>}
            {items.map((item, index) => {
              const header = item.group !== lastGroup ? t(`suite.palette.groups.${item.group}`) : null;
              lastGroup = item.group;
              const Icon = item.icon;

              return (
                <li key={item.key} data-index={index}>
                  {header && <div className="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">{header}</div>}
                  <div
                    role="option"
                    aria-selected={index === active}
                    onMouseEnter={() => setActive(index)}
                    onClick={() => go(item)}
                    className={cn('flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm', index === active && 'bg-accent text-accent-foreground')}
                  >
                    <Icon className="size-4 shrink-0 text-muted-foreground" />
                    <span className="min-w-0 flex-1 truncate">{item.label}{item.detail && <span className="ms-2 text-xs text-muted-foreground">{item.detail}</span>}</span>
                    {item.onRemove && (
                      <button type="button" className="rounded p-1 text-muted-foreground hover:text-foreground" aria-label={t('suite.palette.remove')} onClick={(event) => { event.stopPropagation(); item.onRemove?.(); }}><X className="size-3.5" /></button>
                    )}
                    {index === active && <CornerDownLeft className="size-3.5 text-muted-foreground" />}
                  </div>
                </li>
              );
            })}
          </ul>
          <form className="flex items-center gap-2 border-t bg-muted/40 px-3 py-2" onSubmit={(event) => { event.preventDefault(); saveScope(); }}>
            <Pin className="size-4 text-muted-foreground" />
            <Input value={save.data.name} onChange={(event) => save.setData('name', event.target.value)} placeholder={t('suite.palette.save_placeholder')} className="h-8 flex-1" aria-invalid={Boolean(save.errors.name)} />
            <Button type="submit" size="sm" variant="outline" disabled={save.processing || save.data.name.trim() === ''}>{t('suite.palette.save')}</Button>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
