import { useEffect, useMemo, useRef, useState, type DragEvent } from 'react';
import { FileText, Upload, X } from 'lucide-react';
import { useLocale, useT } from '@/lib/i18n';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Props {
  id?: string;
  files: File[];
  onChange: (files: File[]) => void;
  accept?: string;
  multiple?: boolean;
  /** On phones, open the camera ("environment") or the selfie camera ("user"). */
  capture?: 'environment' | 'user';
  /** Most files kept when `multiple` (default 10). */
  max?: number;
  invalid?: boolean;
}

function size(bytes: number, locale: string): string {
  return bytes >= 1048576 ? `${formatNumber((bytes / 1048576).toFixed(1), locale)} MB` : `${formatNumber(Math.max(1, Math.round(bytes / 1024)), locale)} KB`;
}

/**
 * Baydaq file field: a drop zone with a choose button, the chosen files listed (image previews)
 * with a remove button each. Replaces the browser's bare file input.
 */
export default function FileField({ id, files, onChange, accept, multiple = false, capture, max = 10, invalid }: Props) {
  const t = useT();
  const { locale } = useLocale();
  const input = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);
  const previews = useMemo(() => files.map((file) => (file.type.startsWith('image/') ? URL.createObjectURL(file) : null)), [files]);

  useEffect(() => () => previews.forEach((url) => url && URL.revokeObjectURL(url)), [previews]);

  const add = (picked: File[]) => onChange(multiple ? [...files, ...picked].slice(0, max) : picked.slice(0, 1));
  const onDrop = (event: DragEvent) => {
    event.preventDefault();
    setDragging(false);
    add(Array.from(event.dataTransfer.files));
  };

  return (
    <div className="flex flex-col gap-2">
      <div
        onDragOver={(event) => { event.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={onDrop}
        aria-invalid={invalid || undefined}
        className={cn(
          'flex flex-wrap items-center gap-2 rounded-lg border border-dashed px-3 py-2.5 text-sm transition-colors aria-invalid:border-destructive',
          dragging ? 'border-primary bg-primary/5' : 'border-input',
        )}
      >
        <input ref={input} id={id} type="file" className="sr-only" accept={accept} multiple={multiple} capture={capture} onChange={(event) => { add(Array.from(event.target.files ?? [])); event.target.value = ''; }} />
        <button type="button" onClick={() => input.current?.click()} className="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 font-medium outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50">
          <Upload className="size-4" />{t('common.ui.files_choose')}
        </button>
        <span className="text-muted-foreground">{files.length ? t('common.ui.files_selected', { count: formatNumber(files.length, locale) }) : t('common.ui.files_drop')}</span>
      </div>
      {files.length > 0 && (
        <ul className="flex flex-col gap-1.5">
          {files.map((file, index) => (
            <li key={`${file.name}-${index}`} className="flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm">
              {previews[index] ? <img src={previews[index]!} alt="" className="size-8 rounded object-cover" /> : <FileText className="size-5 text-muted-foreground" />}
              <span className="min-w-0 flex-1 truncate">{file.name}</span>
              <span dir="ltr" className="text-xs text-muted-foreground tabular-nums">{size(file.size, locale)}</span>
              <button type="button" aria-label={t('common.actions.remove')} onClick={() => onChange(files.filter((_, i) => i !== index))} className="rounded p-0.5 text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50">
                <X className="size-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
