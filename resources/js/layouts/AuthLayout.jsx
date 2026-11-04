import { Head } from '@inertiajs/react';
import BrandMark from '@/components/platform/BrandMark';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import PlatformShell from '@/layouts/PlatformShell';
import { useT } from '@/lib/i18n';

/** Centered card for sign-in, password and account-setup screens. */
export default function AuthLayout({ title, description, children }) {
  const t = useT();

  return (
    <PlatformShell>
      <Head title={title} />
      <div className="flex min-h-svh flex-col">
        <header className="flex items-center justify-between px-4 py-3 sm:px-6">
          <a href="/" className="flex items-center gap-2 text-walnut">
            <BrandMark className="size-7" />
            <span className="font-semibold">{t('common.brand')}</span>
          </a>
          <LocaleSwitcher />
        </header>
        <main className="flex flex-1 items-start justify-center px-4 pt-6 pb-16 sm:items-center sm:pt-0">
          <Card className="w-full max-w-sm">
            <CardHeader>
              <CardTitle className="text-lg">{title}</CardTitle>
              {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>{children}</CardContent>
          </Card>
        </main>
      </div>
    </PlatformShell>
  );
}
