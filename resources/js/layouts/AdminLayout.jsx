import { Head, Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Menu } from 'lucide-react';
import BrandMark from '@/components/platform/BrandMark';
import UserMenu from '@/components/platform/UserMenu';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import PlatformShell from '@/layouts/PlatformShell';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Admin navigation. `permission` hides an item from team members who lack it
 * (the system admin has every permission); the server still checks each route.
 */
const NAV = [
  { href: '/admin', label: 'admin.nav.dashboard', icon: LayoutDashboard, exact: true },
];

function NavLinks({ onNavigate }) {
  const t = useT();
  const { url, props } = usePage();
  const permissions = props.auth.user.permissions;
  const path = url.split('?')[0];

  return (
    <nav className="flex flex-col gap-1">
      {NAV.filter((item) => !item.permission || permissions.includes(item.permission)).map((item) => {
        const active = item.exact ? path === item.href : path.startsWith(item.href);

        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
              active ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-foreground',
            )}
          >
            <item.icon className="size-4" />
            {t(item.label)}
          </Link>
        );
      })}
    </nav>
  );
}

function SidebarBody({ onNavigate }) {
  const t = useT();

  return (
    <div className="flex h-full flex-col gap-6 bg-sidebar p-4 text-sidebar-foreground">
      <Link href="/admin" className="flex items-center gap-2 px-2 text-ivory" onClick={onNavigate}>
        <BrandMark className="size-7 text-ivory" />
        <span className="font-semibold">{t('common.platform')}</span>
      </Link>
      <NavLinks onNavigate={onNavigate} />
    </div>
  );
}

export default function AdminLayout({ title, actions, children }) {
  const t = useT();
  const { dir } = useLocale();

  return (
    <PlatformShell>
      <Head title={title} />
      <div className="flex min-h-svh">
        <aside className="sticky top-0 hidden h-svh w-60 shrink-0 lg:block">
          <SidebarBody />
        </aside>
        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-10 flex h-14 items-center gap-2 border-b bg-background/90 px-4 backdrop-blur sm:px-6">
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="lg:hidden" aria-label={t('common.nav.menu')}>
                  <Menu />
                </Button>
              </SheetTrigger>
              <SheetContent side={dir === 'rtl' ? 'right' : 'left'} className="w-64 border-0 p-0" dir={dir}>
                <SheetTitle className="sr-only">{t('common.nav.menu')}</SheetTitle>
                <SidebarBody />
              </SheetContent>
            </Sheet>
            <h1 className="truncate text-base font-semibold">{title}</h1>
            <div className="ms-auto flex items-center gap-2">
              {actions}
              <UserMenu />
            </div>
          </header>
          <main className="flex-1 p-4 sm:p-6">{children}</main>
        </div>
      </div>
    </PlatformShell>
  );
}
