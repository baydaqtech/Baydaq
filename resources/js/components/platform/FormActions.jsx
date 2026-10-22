import { Link } from '@inertiajs/react';
import { Button, buttonVariants } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/** Save + cancel at the foot of an admin form. */
export default function FormActions({ processing, cancelHref, saveLabel }) {
  const t = useT();

  return (
    <div className="flex flex-wrap items-center gap-2 border-t pt-4">
      <Button type="submit" disabled={processing}>
        {processing ? t('admin.common.saving') : (saveLabel ?? t('admin.common.save'))}
      </Button>
      {cancelHref && (
        <Link href={cancelHref} className={buttonVariants({ variant: 'ghost' })}>{t('admin.common.cancel')}</Link>
      )}
    </div>
  );
}
