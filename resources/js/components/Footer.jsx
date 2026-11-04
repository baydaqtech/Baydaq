import { usePage } from '@inertiajs/react';
import { useLocale, useT } from '../lib/i18n';

export default function Footer() {
  const t = useT();
  const { locale } = useLocale();
  const onLanding = usePage().component === 'Landing';
  const to = (id) => (onLanding ? `#${id}` : `/#${id}`);
  const year = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en', { useGrouping: false }).format(new Date().getFullYear());

  return (
    <footer>
      <div className="wrap">
        <div className="wordmark" aria-hidden="true">{t('landing.footer.wordmark')}</div>
        <div className="row">
          <span>{t('landing.footer.rights', { year })}</span>
          <ul>
            <li><a href={to('services')}>{t('landing.nav.services')}</a></li>
            <li><a href="/products">{t('landing.nav.products')}</a></li>
            <li><a href={to('work')}>{t('landing.nav.work')}</a></li>
            <li><a href={to('contact')}>{t('landing.nav.contact')}</a></li>
            <li><a href="/login">{t('landing.nav.login')}</a></li>
          </ul>
          <span className="mono">1. e4 e5 2. Nf3</span>
        </div>
      </div>
    </footer>
  );
}
