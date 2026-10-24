import { Link } from '@inertiajs/react';
import { Button, buttonVariants } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

interface Props {
  processing?: boolean;
  cancelHref?: string;
  saveLabel?: string;
}

/** Save + cancel at the foot of a form. */
export default function FormActions({ processing, cancelHref, saveLabel }: Props) {
  const t = useT();

  return (
    <div className="flex flex-wrap items-center gap-2 border-t pt-4">
      <Button type="submit" disabled={processing}>
        {processing ? t('common.actions.saving') : (saveLabel ?? t('common.actions.save'))}
      </Button>
      {cancelHref && (
        <Link href={cancelHref} className={buttonVariants({ variant: 'ghost' })}>{t('common.actions.cancel')}</Link>
      )}
    </div>
  );
}
