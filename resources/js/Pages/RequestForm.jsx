import { Form, usePage } from '@inertiajs/react';
import SitePage from '../components/SitePage';
import { useT } from '../lib/i18n';

/** The public "request a partnership" / "request a service" form. */
export default function RequestForm({ type, services }) {
  const t = useT();
  const { status } = usePage().props;
  const other = type === 'service' ? 'partnership' : 'service';

  return (
    <SitePage title={t(`requests.${type}.title`)} lede={t(`requests.${type}.lede`)}>
      <div className="request-card">
        <Form action={`/request/${type}`} method="post" options={{ preserveScroll: true }} resetOnSuccess className="request-form">
          {({ errors, processing }) => (
            <>
              <div className="field">
                <label htmlFor="r-name">{t('requests.fields.name')}</label>
                <input id="r-name" name="name" type="text" autoComplete="name" required aria-invalid={!!errors.name} />
                {errors.name && <span className="field-error">{errors.name}</span>}
              </div>
              <div className="field">
                <label htmlFor="r-company">{t('requests.fields.company')}</label>
                <input id="r-company" name="company" type="text" autoComplete="organization" aria-invalid={!!errors.company} />
                {errors.company && <span className="field-error">{errors.company}</span>}
              </div>
              <div className="field">
                <label htmlFor="r-email">{t('requests.fields.email')}</label>
                <input id="r-email" name="email" type="email" autoComplete="email" required aria-invalid={!!errors.email} />
                {errors.email && <span className="field-error">{errors.email}</span>}
              </div>
              <div className="field">
                <label htmlFor="r-phone">{t('requests.fields.phone')}</label>
                <input id="r-phone" name="phone" type="tel" dir="ltr" autoComplete="tel" aria-invalid={!!errors.phone} />
                {errors.phone && <span className="field-error">{errors.phone}</span>}
              </div>

              {type === 'service' && (
                <fieldset className="field full request-services">
                  <legend>{t('requests.fields.services')}</legend>
                  <div className="request-options">
                    {services.map((service) => (
                      <label key={service.key} className="request-option">
                        <input type="checkbox" name="services[]" value={service.key} />
                        <span>{service.name}</span>
                      </label>
                    ))}
                  </div>
                  {(errors.services || errors['services.0']) && (
                    <span className="field-error">{errors.services || errors['services.0']}</span>
                  )}
                </fieldset>
              )}

              <div className="field full">
                <label htmlFor="r-message">{t(`requests.${type}.message`)}</label>
                <textarea id="r-message" name="message" required aria-invalid={!!errors.message} />
                {errors.message && <span className="field-error">{errors.message}</span>}
              </div>

              {/* Honeypot: off-screen and skipped by keyboard and screen readers; bots fill it in. */}
              <div className="hp" aria-hidden="true">
                <label htmlFor="r-website">{t('requests.fields.website')}</label>
                <input id="r-website" name="website" type="text" tabIndex={-1} autoComplete="off" />
              </div>

              <div className="form-foot">
                <button className="btn btn-gold" type="submit" disabled={processing}>
                  {processing ? t('requests.sending') : t('requests.submit')} <span className="arr" aria-hidden="true">←</span>
                </button>
                <span className="form-status" role="status">{status}</span>
              </div>
              <p className="request-note">{t('requests.privacy')}</p>
            </>
          )}
        </Form>
        <a className="request-switch" href={`/request/${other}`}>{t(`requests.${type}.switch`)}</a>
      </div>
    </SitePage>
  );
}
