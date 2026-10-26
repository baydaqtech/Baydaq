import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * Editable list of rows (invoice items, milestones, team, file links).
 * `renderRow(item, update(field, value), index)` draws one row's fields.
 */
export default function Repeater({ items, onChange, blank, renderRow, addLabel, minRows = 0 }) {
  const t = useT();

  const update = (index) => (field, value) => {
    onChange(items.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
  };

  return (
    <div className="flex flex-col gap-3">
      {items.map((item, index) => (
        <div key={item.id ?? `new-${index}`} className="flex items-start gap-2 rounded-lg border bg-card p-3">
          <div className="min-w-0 flex-1">{renderRow(item, update(index), index)}</div>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            className="mt-6 text-muted-foreground hover:text-destructive"
            disabled={items.length <= minRows}
            onClick={() => onChange(items.filter((_, i) => i !== index))}
            aria-label={t('common.actions.remove')}
          >
            <Trash2 />
          </Button>
        </div>
      ))}
      <div>
        <Button type="button" variant="outline" size="sm" onClick={() => onChange([...items, { ...blank }])}>
          <Plus data-icon="inline-start" />
          {addLabel ?? t('common.actions.add_row')}
        </Button>
      </div>
    </div>
  );
}
