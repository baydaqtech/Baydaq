import { forwardRef, type ComponentProps, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface Props extends ComponentProps<'button'> {
  icon: ReactNode;
  /** Shown muted when nothing is chosen yet. */
  placeholder: string;
  display?: string | null;
  invalid?: boolean;
}

/**
 * The closed state of Baydaq pickers (date, month, time, colour, combobox): looks like an input,
 * opens a popover. Styled to match the shadcn select trigger.
 */
const PickerTrigger = forwardRef<HTMLButtonElement, Props>(({ icon, placeholder, display, invalid, className, ...props }, ref) => (
  <button
    ref={ref}
    type="button"
    aria-invalid={invalid || undefined}
    className={cn(
      'flex h-8 w-full items-center gap-2 rounded-lg border border-input bg-transparent px-2.5 text-start text-sm whitespace-nowrap transition-colors outline-none select-none',
      'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
      'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:bg-input/30 dark:hover:bg-input/50',
      '[&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground',
      className,
    )}
    {...props}
  >
    {icon}
    <span className={cn('min-w-0 flex-1 truncate', !display && 'text-muted-foreground')}>{display || placeholder}</span>
  </button>
));

PickerTrigger.displayName = 'PickerTrigger';

export default PickerTrigger;
