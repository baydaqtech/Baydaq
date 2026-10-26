import { useState, type ReactNode } from 'react';
import { router, type Method } from '@inertiajs/core';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
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
  children: ReactNode;
}

/**
 * A button that asks first, then sends the request (e.g. approve, mark paid, void).
 */
export default function ConfirmAction({ href, method = 'post', title, description, confirmLabel, variant = 'default', size, children, data }: Props) {
  const t = useT();
  const { dir } = useLocale();
  const [processing, setProcessing] = useState(false);

  function confirm() {
    router.visit(href, {
      method,
      data: data as never,
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
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common.actions.cancel')}</AlertDialogCancel>
          <AlertDialogAction onClick={confirm} variant={variant === 'destructive' ? 'destructive' : 'default'}>{confirmLabel ?? t('common.actions.confirm')}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
