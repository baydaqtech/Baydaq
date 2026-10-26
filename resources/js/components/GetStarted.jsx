import { useCopy, useLocale, useT } from '../lib/i18n';
import { formatNumber } from '../lib/format';

/** "How it works" on the platform: request → review → account → sign in. */
export default function GetStarted() {
  const t = useT();
  const { locale } = useLocale();
  const steps = useCopy('landing.start.steps');

  return (
    <section className="block" id="start" aria-labelledby="start-title" style={{ paddingTop: 0 }}>
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.start.eyebrow')}</span>
          <h2 id="start-title">{t('landing.start.title')}</h2>
          <p>{t('landing.start.lede')}</p>
        </div>
        <ol className="start-steps">
          {steps.map((step, i) => (
            <li key={step.title}>
              <span className="start-n" aria-hidden="true">{formatNumber(i + 1, locale)}</span>
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
