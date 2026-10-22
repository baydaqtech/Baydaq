import { Languages } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Switches to the other language. A full page load (plain link), so the page
 * direction and every translated string reload together.
 */
export default function LocaleSwitcher({ className }) {
  const t = useT();
  const { locale, localeUrls } = useLocale();
  const other = locale === 'ar' ? 'en' : 'ar';

  return (
    <a href={localeUrls[other]} lang={other} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), className)}>
      <Languages data-icon="inline-start" />
      {t(`common.language.${other}`)}
    </a>
  );
}
