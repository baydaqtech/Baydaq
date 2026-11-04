import { useT } from '../lib/i18n';
import ServiceCard from './ServiceCard';

/** Baydaq Platform services from the catalog (email, OTP, SMS, ...). */
export default function PlatformSection({ services }) {
  const t = useT();

  if (!services.length) {
    return null;
  }

  return (
    <section className="block" id="platform" aria-labelledby="platform-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.platform.eyebrow')}</span>
          <h2 id="platform-title">{t('landing.platform.title')}</h2>
          <p>{t('landing.platform.lede')}</p>
        </div>
        <div className="ps-grid">
          {services.map((service) => <ServiceCard key={service.key} service={service} />)}
        </div>
        <div className="sec-more">
          <a className="btn btn-gold" href="/request/service">{t('landing.platform.request')}</a>
          <a className="btn btn-line" href="/services">{t('landing.platform.all')}</a>
        </div>
      </div>
    </section>
  );
}
