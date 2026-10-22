import { useCopy, useT } from '../lib/i18n';

// The four services, each told by a chess piece; the copy comes from lang/*/landing.php in this order.
export const PIECES = ['queen', 'knight', 'rook', 'bishop'];

export function useServiceCards() {
  return useCopy('landing.services').map((service, i) => ({ ...service, piece: PIECES[i] }));
}

export default function Services() {
  const t = useT();
  const services = useServiceCards();

  return (
    <section className="block" id="services" aria-labelledby="services-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.services_head.eyebrow')}</span>
          <h2 id="services-title">{t('landing.services_head.title')}</h2>
          <p>{t('landing.services_head.lede')}</p>
        </div>

        <div className="services">
          {services.map((s) => (
            <article className="svc" key={s.piece}>
              {/* The mini board is filled in by the page-motion script, as in the prototype. */}
              <div className="mini" data-piece={s.piece} aria-hidden="true" />
              <div className="svc-body">
                <span className="who mono">{s.tag}</span>
                <h3>{s.title}</h3>
                <p>{s.desc}</p>
                <ul className="tags">
                  {s.tags.map((tag) => <li key={tag}>{tag}</li>)}
                </ul>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
