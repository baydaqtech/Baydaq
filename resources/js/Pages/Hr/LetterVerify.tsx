import { Head } from '@inertiajs/react';
import { BadgeCheck, ShieldX } from 'lucide-react';
import DetailList from '@/components/platform/DetailList';
import LocaleSwitcher from '@/components/platform/LocaleSwitcher';
import { Card, CardContent } from '@/components/ui/card';
import PlatformShell from '@/layouts/PlatformShell';
import { formatDate, formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Props {
  letter: {
    reference: string; kind: 'salary' | 'experience'; valid: boolean; issued_at: string; revoked_at: string | null;
    company: string | null; employee: string | null; job: string | null; hired_on: string | null; left_on: string | null; salary_total: string | null;
  };
}

/** What the QR code on a printed HR letter opens (H12): whether the letter is genuine and still stands. */
export default function LetterVerify({ letter }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const v = (field: string) => t(`hr.letters.verify.${field}`);

  return (
    <PlatformShell>
      <Head title={`${v('title')} · ${letter.reference}`} />
      <div className="mx-auto flex min-h-svh max-w-md flex-col gap-4 px-4 py-6">
        <div className="flex justify-end"><LocaleSwitcher className="" /></div>
        <Card className={cn('border-2', letter.valid ? 'border-emerald-500' : 'border-red-500')}>
          <CardContent className="flex flex-col gap-4">
            <div className="flex items-center gap-3">
              {letter.valid ? <BadgeCheck className="size-10 shrink-0 text-emerald-600" /> : <ShieldX className="size-10 shrink-0 text-red-600" />}
              <div>
                <p className="text-lg font-semibold">{letter.valid ? v('valid') : v('revoked')}</p>
                <p className="text-sm text-muted-foreground">{letter.valid ? v('valid_hint') : v('revoked_hint')}</p>
              </div>
            </div>
            <DetailList items={[
              { label: v('kind'), value: t(`hr.letter_kind.${letter.kind}`) },
              { label: v('reference'), value: <span dir="ltr">{letter.reference}</span> },
              { label: v('company'), value: letter.company },
              { label: v('employee'), value: letter.employee },
              { label: v('job'), value: letter.job },
              { label: v('hired_on'), value: formatDate(letter.hired_on, locale) },
              letter.left_on ? { label: v('left_on'), value: formatDate(letter.left_on, locale) } : null,
              letter.salary_total ? { label: v('salary_total'), value: formatMoney(letter.salary_total, locale) } : null,
              { label: v('issued_at'), value: formatDate(letter.issued_at, locale) },
              letter.revoked_at ? { label: v('revoked_at'), value: formatDate(letter.revoked_at, locale) } : null,
            ]} />
          </CardContent>
        </Card>
        <p className="text-center text-xs text-muted-foreground">{v('footer')}</p>
      </div>
    </PlatformShell>
  );
}
