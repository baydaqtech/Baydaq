import { Link, usePage } from '@inertiajs/react';
import { Rocket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
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
  setup: { done: number; total: number; next: { key: string; title: string; hint: string; url: string } | null } | null;
}

/** How far the account's setup has got, with the next step one click away. */
function SetupCard({ setup }: { setup: NonNullable<Props['setup']> }) {
  const t = useT();
  const url = useSuiteUrl();
  const s = (key: string, replace?: Record<string, string>) => t(`suite.start.${key}`, replace);

  return (
    <Card className="mb-6 border-primary/30">
      <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <Rocket className="size-8 shrink-0 text-primary" />
        <div className="min-w-0 flex-1">
          <p className="font-semibold">{s('card_title', { done: String(setup.done), total: String(setup.total) })}</p>
          {setup.next && <p className="text-sm text-muted-foreground">{s('card_next', { step: setup.next.title })}</p>}
          <Progress className="mt-2 h-1.5" value={(setup.done / Math.max(1, setup.total)) * 100} />
        </div>
        <div className="flex gap-2">
          {setup.next && <Button asChild size="sm"><Link href={url(setup.next.url)}>{s('do_it')}</Link></Button>}
          <Button asChild size="sm" variant="outline"><Link href={url('/start')}>{s('all_steps')}</Link></Button>
        </div>
      </CardContent>
    </Card>
  );
}

export default function SuiteHome({ modules, setup }: Props) {
  const t = useT();
  const { auth } = usePage<SuitePageProps>().props;

  return (
    <SuiteLayout title={t('suite.home.title')}>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold">{t('suite.home.welcome', { name: auth.user?.name ?? '' })}</h1>
      </div>
      {setup && <SetupCard setup={setup} />}
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
