import { Link, router } from '@inertiajs/react';
import { Building2, ChevronDown, MapPin, Network } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale, useT } from '@/lib/i18n';
import { useSuite, useSuiteUrl } from '@/lib/suite';

/**
 * The scope lens: [Organization ▾] › [Company ▾] › [Branches ▾]. A level is hidden when the user
 * has only one choice there, so someone limited to one company sees just its branches.
 */
export default function ScopeBar() {
  const t = useT();
  const { dir } = useLocale();
  const { scope, tree } = useSuite();
  const url = useSuiteUrl();
  const path = window.location.pathname.replace(/^\/suite\/[^/]+/, '') || '/';

  const companies = tree.flatMap((organization) => organization.companies);
  const currentCompanies = scope.level === 'organization'
    ? tree.find((organization) => organization.slug === scope.organization?.slug)?.companies ?? []
    : companies;
  const scopedBranches = scope.level === 'company'
    ? companies.find((company) => company.slug === scope.company?.slug)?.branches ?? []
    : currentCompanies.flatMap((company) => company.branches);

  const branchLabel = scope.branches.length === 0
    ? t('suite.scope.all_branches', { count: scopedBranches.length })
    : scopedBranches.filter((branch) => scope.branches.includes(branch.id)).map((branch) => branch.name).join('، ');

  function toggleBranch(id: number) {
    const next = scope.branches.includes(id) ? scope.branches.filter((branch) => branch !== id) : [...scope.branches, id];
    router.visit(url(path, scope.segment, next.length === scopedBranches.length ? [] : next), { preserveScroll: true });
  }

  return (
    <nav aria-label={t('suite.scope.change')} className="flex min-w-0 items-center gap-1 text-sm">
      {tree.length > 1 && (
        <DropdownMenu dir={dir}>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="max-w-48 gap-1">
              <Network className="size-4 shrink-0" />
              <span className="truncate">{scope.organization?.name ?? t('suite.scope.all')}</span>
              <ChevronDown className="size-3 opacity-60" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start">
            <DropdownMenuItem asChild><Link href={url(path, 'all', [])}>{t('suite.scope.all')}</Link></DropdownMenuItem>
            <DropdownMenuSeparator />
            {tree.map((organization) => (
              <DropdownMenuItem key={organization.slug} asChild>
                <Link href={url(path, `o-${organization.slug}`, [])}>{organization.name}</Link>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
      {companies.length > 1 && (
        <>
          {tree.length > 1 && <span className="text-muted-foreground">›</span>}
          <DropdownMenu dir={dir}>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm" className="max-w-48 gap-1">
                <Building2 className="size-4 shrink-0" />
                <span className="truncate">{scope.company?.name ?? t('suite.scope.pick_company')}</span>
                <ChevronDown className="size-3 opacity-60" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              {currentCompanies.map((company) => (
                <DropdownMenuItem key={company.slug} asChild>
                  <Link href={url(path, `c-${company.slug}`, [])}>{company.name}</Link>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </>
      )}
      {scopedBranches.length > 1 && (
        <>
          <span className="text-muted-foreground">›</span>
          <DropdownMenu dir={dir}>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm" className="max-w-56 gap-1">
                <MapPin className="size-4 shrink-0" />
                <span className="truncate">{branchLabel}</span>
                <ChevronDown className="size-3 opacity-60" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-56">
              <DropdownMenuLabel>{t('suite.scope.branches')}</DropdownMenuLabel>
              <DropdownMenuItem onSelect={() => router.visit(url(path, scope.segment, []), { preserveScroll: true })}>
                {t('suite.scope.all_branches', { count: scopedBranches.length })}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              {scopedBranches.map((branch) => (
                <DropdownMenuItem key={branch.id} onSelect={(event) => { event.preventDefault(); toggleBranch(branch.id); }} className="gap-2">
                  <Checkbox checked={scope.branches.length === 0 || scope.branches.includes(branch.id)} aria-hidden />
                  <span className="flex-1">{branch.name}</span>
                  <button
                    type="button"
                    className="text-xs text-muted-foreground hover:text-foreground"
                    onClick={(event) => { event.stopPropagation(); router.visit(url(path, scope.segment, [branch.id]), { preserveScroll: true }); }}
                  >
                    {t('suite.scope.only_this')}
                  </button>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </>
      )}
    </nav>
  );
}
