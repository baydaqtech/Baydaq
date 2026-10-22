import { cn } from '@/lib/utils';

/** The gold pawn mark (same artwork as the landing loader). */
export default function BrandMark({ className }) {
  return (
    <svg viewBox="0 0 100 100" aria-hidden="true" className={cn('size-8', className)}>
      <rect x="20" y="78" width="60" height="12" rx="2.5" fill="currentColor" />
      <rect x="27" y="64" width="46" height="10" rx="2.5" fill="currentColor" />
      <rect x="34" y="50" width="32" height="10" rx="2.5" fill="currentColor" />
      <rect x="41" y="40" width="18" height="6" rx="3" fill="currentColor" />
      <circle cx="50" cy="23" r="13" fill="#C8902F" />
    </svg>
  );
}
