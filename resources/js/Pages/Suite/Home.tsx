import { usePage } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import type { SuitePageProps } from '@/types/suite';

interface ModuleCard {
  module: string;
  label: string;
  active: boolean;
  quantity: number;
  unit: string;
  ends_on: string | null;
}

interface Props {
  modules: ModuleCard[];
  available: { module: string; label: string }[];
}

export default function SuiteHome({ modules }: Props) {
  const t = useT();
  const { auth } = usePage<SuitePageProps>().props;

  return (
    <SuiteLayout title={t('suite.home.title')}>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold">{t('suite.home.welcome', { name: auth.user?.name ?? '' })}</h1>
      </div>
      {modules.length === 0 ? (
        <Card><CardContent className="py-10 text-center text-muted-foreground">{t('suite.home.no_modules')}</CardContent></Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {modules.map((module) => (
            <Card key={module.module} className={module.active ? '' : 'opacity-70'}>
              <CardContent>
                <h2 className="font-semibold">{module.label}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  {module.quantity} {t(`suite.billing_unit.${module.unit}`)}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </SuiteLayout>
  );
}
