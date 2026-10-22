import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

/**
 * Label + input + hint + validation error, for forms posted with Inertia's <Form>.
 */
export default function TextField({ name, label, error, hint, className, ...inputProps }) {
  const id = inputProps.id ?? `field-${name}`;

  return (
    <Field data-invalid={error ? true : undefined} className={className}>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input id={id} name={name} aria-invalid={error ? true : undefined} {...inputProps} />
      {hint && !error && <FieldDescription>{hint}</FieldDescription>}
      {error && <FieldError>{error}</FieldError>}
    </Field>
  );
}
