import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { cn } from '@/lib/utils';

/** Label + control + hint + error for controlled (useForm) admin forms. */
export default function FormField({ label, htmlFor, error, hint, className, children }) {
  return (
    <Field data-invalid={error ? true : undefined} className={cn('gap-1.5', className)}>
      {label && <FieldLabel htmlFor={htmlFor}>{label}</FieldLabel>}
      {children}
      {hint && !error && <FieldDescription>{hint}</FieldDescription>}
      {error && <FieldError>{error}</FieldError>}
    </Field>
  );
}
