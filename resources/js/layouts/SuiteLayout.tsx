import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BrandMark from '@/components/platform/BrandMark';
import UserMenu from '@/components/platform/UserMenu';
import ScopeBar from '@/components/suite/ScopeBar';
import SuiteNav from '@/components/suite/SuiteNav';
import PlatformShell from '@/layouts/PlatformShell';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';

interface Props {
  title: string;
  actions?: ReactNode;
  children: ReactNode;
}

/** Business suite: brand, the scope lens, and a centered content column. */
export default function SuiteLayout({ title, actions, children }: Props) {
  const t = useT();
  const url = useSuiteUrl();

  return (
    <PlatformShell>
      <Head title={title} />
      <div className="flex min-h-svh flex-col">
        <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
          <div className="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 sm:px-6">
            <Link href={url('/')} className="flex items-center gap-2 text-walnut">
              <BrandMark className="size-7" />
              <span className="hidden font-semibold sm:inline">{t('suite.title')}</span>
            </Link>
            <ScopeBar />
            <div className="ms-auto flex items-center gap-2">
              {actions}
              <UserMenu />
            </div>
          </div>
          <SuiteNav />
        </header>
        <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
          <h1 className="sr-only">{title}</h1>
          {children}
        </main>
      </div>
    </PlatformShell>
  );
}
