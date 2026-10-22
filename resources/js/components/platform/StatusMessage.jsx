import { cn } from '@/lib/utils';

/** A one-line notice above a form, e.g. "reset link sent". Renders nothing when empty. */
export default function StatusMessage({ children, className }) {
  if (!children) {
    return null;
  }

  return (
    <p role="status" className={cn('mb-4 rounded-lg bg-accent px-3 py-2 text-sm text-accent-foreground', className)}>
      {children}
    </p>
  );
}
