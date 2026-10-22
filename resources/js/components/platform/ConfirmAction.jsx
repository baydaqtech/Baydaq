import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useLocale, useT } from '@/lib/i18n';

/**
 * A button that asks first, then sends the request (e.g. approve, mark paid, void).
 */
export default function ConfirmAction({ href, method = 'post', title, description, confirmLabel, variant = 'default', children, data }) {
  const t = useT();
  const { dir } = useLocale();
  const [processing, setProcessing] = useState(false);

  function confirm() {
    router.visit(href, {
      method,
      data,
      preserveScroll: true,
      onStart: () => setProcessing(true),
      onFinish: () => setProcessing(false),
    });
  }

  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button variant={variant} disabled={processing}>{children}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent dir={dir}>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('admin.common.cancel')}</AlertDialogCancel>
          <AlertDialogAction onClick={confirm} variant={variant}>{confirmLabel ?? t('admin.common.confirm')}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
