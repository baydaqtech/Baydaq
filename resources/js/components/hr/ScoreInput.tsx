import { cn } from '@/lib/utils';

/** A 1–5 score picker for performance reviews. */
export default function ScoreInput({ value, onChange, disabled }: { value: number | undefined; onChange: (score: number) => void; disabled?: boolean }) {
  return (
    <div className="flex gap-1" dir="ltr">
      {[1, 2, 3, 4, 5].map((score) => (
        <button key={score} type="button" disabled={disabled} onClick={() => onChange(score)} className={cn('size-8 rounded-md border text-sm', value === score ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted')}>{score}</button>
      ))}
    </div>
  );
}
