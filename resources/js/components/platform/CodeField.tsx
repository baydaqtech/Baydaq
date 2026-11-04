import { unstable_OneTimePasswordField as OneTimePasswordField } from 'radix-ui';
import { cn } from '@/lib/utils';

interface Props {
  name: string;
  length?: number;
  autoFocus?: boolean;
  invalid?: boolean;
  /** Submit the surrounding form as soon as the last digit is typed (or a code is pasted). */
  autoSubmit?: boolean;
  'aria-label'?: string;
}

/**
 * A one-time code from an authenticator app: one box per digit, paste and autofill friendly,
 * always left to right. The value is posted under `name` with the surrounding form.
 */
export default function CodeField({ name, length = 6, autoFocus, invalid, autoSubmit = true, 'aria-label': label }: Props) {
  return (
    <OneTimePasswordField.Root
      name={name}
      dir="ltr"
      validationType="numeric"
      autoComplete="one-time-code"
      autoFocus={autoFocus}
      autoSubmit={autoSubmit}
      aria-label={label}
      className="flex justify-center gap-2"
    >
      {Array.from({ length }, (_, index) => (
        <OneTimePasswordField.Input
          key={index}
          aria-invalid={invalid || undefined}
          className={cn(
            'size-11 rounded-lg border border-input bg-transparent text-center text-lg font-semibold tabular-nums transition-colors outline-none',
            'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30',
            'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
          )}
        />
      ))}
      <OneTimePasswordField.HiddenInput />
    </OneTimePasswordField.Root>
  );
}
