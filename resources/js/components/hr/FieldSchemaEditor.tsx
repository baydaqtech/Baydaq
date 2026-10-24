import { Plus, Trash2 } from 'lucide-react';
import FormField from '@/components/platform/FormField';
import SelectInput from '@/components/platform/SelectInput';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import type { DocumentField } from '@/types/hr';
import type { Option } from '@/types/resources';

interface Props {
  fields: DocumentField[];
  onChange: (fields: DocumentField[]) => void;
  types: Option[];
  errors?: Record<string, string>;
}

const blank: DocumentField = { key: '', label_ar: '', label_en: '', type: 'text', required: false, options: [] };

/** Lets the owner design the extra fields of a document type (label in both languages, type, required, options). */
export default function FieldSchemaEditor({ fields, onChange, types, errors = {} }: Props) {
  const t = useT();
  const f = (key: string) => t(`hr.catalog.fields.${key}`);
  const update = (index: number, patch: Partial<DocumentField>) => onChange(fields.map((field, i) => (i === index ? { ...field, ...patch } : field)));

  return (
    <div className="flex flex-col gap-3">
      {fields.map((field, index) => (
        <div key={index} className="grid gap-3 rounded-lg border bg-card p-3 sm:grid-cols-[1fr_1fr_140px_auto_auto]">
          <FormField label={f('field_label_ar')} error={errors[`fields.${index}.label_ar`]}>
            <Input dir="rtl" value={field.label_ar} onChange={(event) => update(index, { label_ar: event.target.value })} />
          </FormField>
          <FormField label={f('field_label_en')} error={errors[`fields.${index}.label_en`]}>
            <Input dir="ltr" value={field.label_en} onChange={(event) => update(index, { label_en: event.target.value })} />
          </FormField>
          <FormField label={f('field_type')} error={errors[`fields.${index}.type`]}>
            <SelectInput value={field.type} onChange={(value) => update(index, { type: value as DocumentField['type'] })} options={types} />
          </FormField>
          <FormField label={f('field_required')}>
            <div className="flex h-8 items-center"><Checkbox checked={field.required} onCheckedChange={(state) => update(index, { required: state === true })} /></div>
          </FormField>
          <Button type="button" variant="ghost" size="icon-sm" className="mt-6 text-muted-foreground hover:text-destructive" onClick={() => onChange(fields.filter((_, i) => i !== index))} aria-label={t('common.actions.remove')}>
            <Trash2 />
          </Button>
          {field.type === 'select' && (
            <FormField label={f('field_options')} className="sm:col-span-5" error={errors[`fields.${index}.options`]}>
              <Textarea rows={2} value={(field.options ?? []).join('\n')} onChange={(event) => update(index, { options: event.target.value.split('\n') })} />
            </FormField>
          )}
        </div>
      ))}
      <div>
        <Button type="button" variant="outline" size="sm" onClick={() => onChange([...fields, { ...blank }])}><Plus data-icon="inline-start" />{t('common.actions.add_row')}</Button>
      </div>
    </div>
  );
}
