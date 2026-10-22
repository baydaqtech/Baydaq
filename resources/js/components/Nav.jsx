import { usePage } from '@inertiajs/react';
import { useLocale, useT } from '../lib/i18n';

export default function Nav() {
  const t = useT();
  const { locale, localeUrls } = useLocale();
  // Section links scroll on the landing page and lead back to it from the other public pages.
  const onLanding = usePage().component === 'Landing';
  const to = (id) => (onLanding ? `#${id}` : `/#${id}`);
  const other = locale === 'ar' ? 'en' : 'ar';

  return (
    <header className="nav">
      <div className="wrap">
        <a className="brand" href={onLanding ? '#top' : '/'} aria-label={t('landing.nav.home')}>
          <svg viewBox="0 0 100 100" aria-hidden="true"><use href="#logo-mark" /></svg>
          <b>{t('common.brand')}</b>
        </a>
        <ul>
          <li><a href={to('services')}>{t('landing.nav.services')}</a></li>
          <li><a href="/products">{t('landing.nav.products')}</a></li>
          <li><a href={to('work')}>{t('landing.nav.work')}</a></li>
          <li><a href={to('about')}>{t('landing.nav.about')}</a></li>
        </ul>
        <div className="nav-actions">
          <a className="nav-lang" href={localeUrls[other]} lang={other} hrefLang={other}>{t(`common.language.${other}`)}</a>
          {/* Sign-in lives in the platform shell: a plain link, so the page fully reloads. */}
          <a className="btn btn-line nav-login" href="/login">{t('landing.nav.login')}</a>
          <a className="btn btn-gold" href={to('contact')}>{t('landing.nav.contact')}</a>
        </div>
      </div>
      <span className="progress" aria-hidden="true" />
    </header>
  );
}
