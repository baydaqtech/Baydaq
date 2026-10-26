import { useLocale, useT } from '../lib/i18n';
import { formatMoney } from '../lib/format';
import { serviceIcon } from '../lib/serviceIcons';

/** One Baydaq Platform service: icon, name, description, pricing model and (when set) the starting price. */
export default function ServiceCard({ service }) {
  const t = useT();
  const { locale } = useLocale();
  const Icon = serviceIcon(service.icon);
  const unit = service.unit ? t(`common.units.${service.unit}`) : null;

  return (
    <article className="ps-card">
      <span className="ps-icon" aria-hidden="true"><Icon strokeWidth={1.6} /></span>
      <h3>{service.name}</h3>
      <p>{service.description}</p>
      <div className="ps-meta">
        <span>{t(`enums.pricing_model.${service.pricing_model}`)}</span>
        {service.default_price && (
          <span className="ps-price">
            {formatMoney(service.default_price, locale)}
            {unit && service.pricing_model === 'per_use' && ` ${t('landing.platform.per_unit', { unit })}`}
          </span>
        )}
      </div>
    </article>
  );
}
