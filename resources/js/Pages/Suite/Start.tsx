import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import PageHeader from '@/components/platform/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useLocale, useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

interface Step { key: string; title: string; hint: string; done: boolean; url: string; order: number }

/**
 * Getting started, as a checklist that reads the account as it is: done steps are ticked, the
 * next one is open with what it is for, and each goes to the page where it is done.
 */
export default function Start({ steps }: { steps: Step[] }) {
  const t = useT();
  const { dir } = useLocale();
  const url = useSuiteUrl();
  const s = (key: string, replace?: Record<string, string>) => t(`suite.start.${key}`, replace);
  const done = steps.filter((step) => step.done).length;
  const next = steps.find((step) => !step.done);
  const Forward = dir === 'rtl' ? ArrowLeft : ArrowRight;

  return (
    <SuiteLayout title={s('title')}>
      <PageHeader title={s('title')} description={s('lede')} />
      <Card className="max-w-3xl">
        <CardContent className="flex flex-col gap-4">
          <div>
            <div className="mb-1 flex justify-between text-sm">
              <span className="font-medium">{next ? s('progress', { done: String(done), total: String(steps.length) }) : s('all_done')}</span>
            </div>
            <Progress className="h-2" value={(done / Math.max(1, steps.length)) * 100} />
          </div>
          <ol className="flex flex-col">
            {steps.map((step, index) => {
              const current = step === next;

              return (
                <li key={step.key} className="flex gap-3">
                  <div className="flex flex-col items-center">
                    <span className={cn(
                      'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums',
                      step.done && 'border-emerald-600 bg-emerald-600 text-white',
                      current && 'border-primary text-primary',
                    )}
                    >
                      {step.done ? <Check className="size-4" /> : index + 1}
                    </span>
                    {index < steps.length - 1 && <span className="my-1 w-px flex-1 bg-border" />}
                  </div>
                  <div className={cn('flex flex-1 flex-col gap-1 pb-5', !current && 'pb-4')}>
                    <span className={cn('font-medium', step.done && 'text-muted-foreground line-through decoration-muted-foreground/50')}>{step.title}</span>
                    {(current || !step.done) && <span className="text-sm text-muted-foreground">{step.hint}</span>}
                    {!step.done && (
                      <Button asChild size="sm" variant={current ? 'default' : 'outline'} className="mt-1 self-start">
                        <Link href={url(step.url)}>{s(current ? 'do_it' : 'open')}<Forward data-icon="inline-end" /></Link>
                      </Button>
                    )}
                  </div>
                </li>
              );
            })}
          </ol>
        </CardContent>
      </Card>
    </SuiteLayout>
  );
}
