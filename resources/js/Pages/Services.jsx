import ServiceCard from '../components/ServiceCard';
import SitePage from '../components/SitePage';
import { useT } from '../lib/i18n';

export default function Services({ services }) {
  const t = useT();

  return (
    <SitePage
      eyebrow={t('landing.platform.eyebrow')}
      title={t('landing.pages.services.title')}
      lede={t('landing.pages.services.lede')}
    >
      <div className="ps-grid">
        {services.map((service) => <ServiceCard key={service.key} service={service} />)}
      </div>
      <div className="page-cta">
        <div>
          <h2>{t('landing.pages.services.cta_title')}</h2>
          <p>{t('landing.pages.services.cta_text')}</p>
        </div>
        <div className="page-cta-btns">
          <a className="btn btn-gold" href="/request/service">{t('landing.platform.request')}</a>
        </div>
      </div>
    </SitePage>
  );
}
