import { useT } from '../lib/i18n';
import { TECH_LOGOS } from '../lib/techLogos';

export default function TechStrip() {
  const t = useT();

  return (
    <div className="stack">
      <div className="wrap">
        <p>{t('landing.tech.title')}</p>
        <ul aria-label={t('landing.tech.label')} dangerouslySetInnerHTML={{ __html: TECH_LOGOS }} />
      </div>
    </div>
  );
}
