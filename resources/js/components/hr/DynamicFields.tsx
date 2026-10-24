import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useLocale, useT } from '@/lib/i18n';
import type { DocumentField } from '@/types/hr';

export type FieldValues = Record<string, string | number | boolean | null>;

interface Props {
  fields: DocumentField[];
  value: FieldValues;
  onChange: (value: FieldValues) => void;
  errors?: Record<string, string>;
}

/** Renders a document type's extra fields from its schema; the server validates the same schema. */
export default function DynamicFields({ fields, value, onChange, errors = {} }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const set = (key: string, next: string | number | boolean | null) => onChange({ ...value, [key]: next });

  return (
    <>
      {fields.map((field) => {
        const label = locale === 'ar' ? field.label_ar : field.label_en;
        const error = errors[`data.${field.key}`];
        const id = `field-${field.key}`;
        const current = value[field.key];

        if (field.type === 'boolean') {
          return (
            <FormField key={field.key} error={error}>
              <label className="flex items-center gap-2 text-sm"><Checkbox checked={Boolean(current)} onCheckedChange={(state) => set(field.key, state === true)} />{label}</label>
            </FormField>
          );
        }

        if (field.type === 'select') {
          return (
            <FormField key={field.key} label={label} htmlFor={id} error={error}>
              <SelectInput id={id} value={String(current ?? '')} onChange={(next) => set(field.key, next)} options={(field.options ?? []).map((option) => ({ value: option, label: option }))} placeholder={t('common.actions.choose')} />
            </FormField>
          );
        }

        return (
          <FormField key={field.key} label={label} htmlFor={id} error={error}>
            <Input
              id={id}
              type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
              dir={field.type === 'text' ? undefined : 'ltr'}
              value={String(current ?? '')}
              onChange={(event) => set(field.key, event.target.value)}
            />
          </FormField>
        );
      })}
    </>
  );
}
