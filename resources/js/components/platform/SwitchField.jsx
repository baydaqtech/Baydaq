import { Checkbox } from '@/components/ui/checkbox';

/** A labelled on/off checkbox for controlled forms. */
export default function SwitchField({ id, label, checked, onChange }) {
  return (
    <label htmlFor={id} className="flex items-center gap-2 text-sm">
      <Checkbox id={id} checked={!!checked} onCheckedChange={(value) => onChange(value === true)} />
      {label}
    </label>
  );
}
