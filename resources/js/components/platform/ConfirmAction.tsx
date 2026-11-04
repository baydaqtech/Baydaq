import { useState, type ReactNode } from 'react';
import { router, type Method } from '@inertiajs/core';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocale, useT } from '@/lib/i18n';

type Variant = 'default' | 'outline' | 'secondary' | 'ghost' | 'destructive' | 'link';

interface Props {
  href: string;
  method?: Method;
  title: ReactNode;
  description?: ReactNode;
  confirmLabel?: string;
  variant?: Variant;
  size?: 'default' | 'xs' | 'sm' | 'lg' | 'icon' | 'icon-xs' | 'icon-sm' | 'icon-lg';
  data?: Record<string, unknown>;
  /** Ask for a short text before sending it (e.g. what was done, why it was refused). */
  note?: { field: string; label: string; required?: boolean };
  children: ReactNode;
}

/**
 * A button that asks first, then sends the request (e.g. approve, mark paid, void). With `note`
 * it also asks for a short text, sent as that field.
 */
export default function ConfirmAction({ href, method = 'post', title, description, confirmLabel, variant = 'default', size, children, data, note }: Props) {
  const t = useT();
  const { dir } = useLocale();
  const [processing, setProcessing] = useState(false);
  const [text, setText] = useState('');

  function confirm() {
    router.visit(href, {
      method,
      data: (note ? { ...data, [note.field]: text } : data) as never,
      preserveScroll: true,
      onStart: () => setProcessing(true),
      onFinish: () => setProcessing(false),
    });
  }

  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button variant={variant} size={size} disabled={processing}>{children}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent dir={dir}>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
        </AlertDialogHeader>
        {note && (
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`confirm-note-${note.field}`}>{note.label}</Label>
            <Textarea id={`confirm-note-${note.field}`} rows={3} value={text} onChange={(event) => setText(event.target.value)} />
          </div>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.actions.cancel')}</AlertDialogCancel>
          <AlertDialogAction onClick={confirm} disabled={note?.required && text.trim() === ''} variant={variant === 'destructive' ? 'destructive' : 'default'}>{confirmLabel ?? t('common.actions.confirm')}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
