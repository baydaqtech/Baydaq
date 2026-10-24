import { Head, Link } from '@inertiajs/react';
import BrandMark from '@/components/platform/BrandMark';
import UserMenu from '@/components/platform/UserMenu';
import PlatformShell from '@/layouts/PlatformShell';
import { useT } from '@/lib/i18n';

/** Client portal: a slim top bar and a centered content column. */
export default function PortalLayout({ title, children }) {
  const t = useT();

  return (
    <PlatformShell>
      <Head title={title} />
      <div className="flex min-h-svh flex-col">
        <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
          <div className="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4 sm:px-6">
            <Link href="/app" className="flex items-center gap-2 text-walnut">
              <BrandMark className="size-7" />
              <span className="font-semibold">{t('common.platform')}</span>
            </Link>
            <nav className="ms-6 hidden items-center gap-4 text-sm sm:flex">
              <Link href="/app" className="text-muted-foreground hover:text-foreground">{t('portal.home.title')}</Link>
              <Link href="/app/statement" className="text-muted-foreground hover:text-foreground">{t('portal.home.statement')}</Link>
            </nav>
            <div className="ms-auto">
              <UserMenu />
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">{children}</main>
      </div>
    </PlatformShell>
  );
}
