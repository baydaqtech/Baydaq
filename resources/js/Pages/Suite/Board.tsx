import { Link } from '@inertiajs/react';
import { Crown } from 'lucide-react';
import BrandMark from '@/components/platform/BrandMark';
import PageHeader from '@/components/platform/PageHeader';
import { Card, CardContent } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import SuiteLayout from '@/layouts/SuiteLayout';
import { useT } from '@/lib/i18n';
import { useSuiteUrl } from '@/lib/suite';
import { cn } from '@/lib/utils';

type Health = 'good' | 'warn' | 'bad' | 'none';

interface Signal { key: string; label: string; value: string; status: 'good' | 'warn' | 'bad' | 'info' }
interface BoardBranch { id: number; code: string; name: string; is_head_office: boolean; health: Health; signals: Signal[] }
interface BoardCompany { id: number; slug: string; name: string; branches: BoardBranch[] }

const PIECE: Record<Health, string> = {
  good: 'text-emerald-700 ring-emerald-500',
  warn: 'text-amber-600 ring-amber-400',
  bad: 'text-red-700 ring-red-500 animate-pulse',
  none: 'text-walnut/60 ring-border',
};

const DOT: Record<Signal['status'], string> = {
  good: 'bg-emerald-500',
  warn: 'bg-amber-400',
  bad: 'bg-red-500',
  info: 'bg-muted-foreground/40',
};

/**
 * The owner board (X1): a chessboard where each company is a rank and each branch a pawn
 * (the head office a crowned piece), coloured by its worst health reading.
 */
export default function Board({ companies }: { companies: BoardCompany[] }) {
  const t = useT();
  const url = useSuiteUrl();
  const totals = companies.flatMap((company) => company.branches).reduce<Record<Health, number>>(
    (sum, branch) => ({ ...sum, [branch.health]: sum[branch.health] + 1 }),
    { good: 0, warn: 0, bad: 0, none: 0 },
  );

  return (
    <SuiteLayout title={t('suite.board.title')}>
      <PageHeader title={t('suite.board.title')} description={t('suite.board.lede')} />
      <div className="mb-4 flex flex-wrap gap-3 text-sm">
        {(['good', 'warn', 'bad', 'none'] as Health[]).map((health) => (
          <span key={health} className="flex items-center gap-1.5">
            <span className={cn('size-3 rounded-full ring-2', PIECE[health].replace('animate-pulse', ''), 'bg-current')} />
            {t(`suite.board.health.${health}`)} · {totals[health]}
          </span>
        ))}
      </div>
      {companies.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">{t('suite.board.empty')}</CardContent></Card>}
      <div className="flex flex-col gap-1 rounded-xl border-8 border-walnut bg-walnut p-1 shadow-sm">
        {companies.map((company, rank) => (
          <section key={company.id} aria-label={company.name}>
            <div className="flex items-center justify-between px-2 py-1 text-xs font-medium text-ivory">
              <Link href={url('/', `c-${company.slug}`, [])} className="hover:underline">{company.name}</Link>
            </div>
            <div className="grid grid-cols-[repeat(auto-fill,minmax(7.5rem,1fr))]">
              {company.branches.map((branch, file) => (
                <Tooltip key={branch.id}>
                  <TooltipTrigger asChild>
                    <Link
                      href={url('/', `c-${company.slug}`, [branch.id])}
                      className={cn(
                        'flex aspect-square flex-col items-center justify-center gap-1 p-2 text-center transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-brass',
                        (rank + file) % 2 === 0 ? 'bg-ivory' : 'bg-[#E7D3AE]',
                      )}
                    >
                      <span className={cn('grid size-12 place-items-center rounded-full bg-background/70 ring-4', PIECE[branch.health])}>
                        {branch.is_head_office ? <Crown className="size-7" /> : <BrandMark className="size-9" />}
                      </span>
                      <span className="line-clamp-2 text-xs font-medium text-walnut">{branch.name}</span>
                      <span className="flex gap-0.5">
                        {branch.signals.map((signal) => <span key={signal.key} className={cn('size-1.5 rounded-full', DOT[signal.status])} />)}
                      </span>
                    </Link>
                  </TooltipTrigger>
                  <TooltipContent className="max-w-60">
                    <div className="mb-1 font-medium">{branch.name}{branch.is_head_office && ` · ${t('suite.board.head_office')}`}</div>
                    {branch.signals.length === 0 && <div>{t('suite.board.health.none')}</div>}
                    <ul className="flex flex-col gap-0.5">
                      {branch.signals.map((signal) => (
                        <li key={signal.key} className="flex items-center justify-between gap-3">
                          <span className="flex items-center gap-1.5"><span className={cn('size-2 rounded-full', DOT[signal.status])} />{signal.label}</span>
                          <span className="font-medium" dir="ltr">{signal.value}</span>
                        </li>
                      ))}
                    </ul>
                  </TooltipContent>
                </Tooltip>
              ))}
            </div>
          </section>
        ))}
      </div>
    </SuiteLayout>
  );
}
