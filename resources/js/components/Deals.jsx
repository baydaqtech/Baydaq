import { useCopy, useT } from '../lib/i18n';

// One piece per way of working, in the order of landing.deals.items.
const DEAL_PIECES = ['rook', 'knight', 'queen'];

/** "What we do": the three ways to work with Baydaq. */
export default function Deals() {
  const t = useT();
  const deals = useCopy('landing.deals.items');

  return (
    <section className="block" id="what-we-do" aria-labelledby="deals-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.deals.eyebrow')}</span>
          <h2 id="deals-title">{t('landing.deals.title')}</h2>
          <p>{t('landing.deals.lede')}</p>
        </div>
        <ol className="deals">
          {deals.map((deal, i) => (
            <li className="deal" key={deal.title}>
              <span className="deal-piece" aria-hidden="true">
                <svg viewBox="0 0 45 45"><use href={`#p-${DEAL_PIECES[i]}`} /></svg>
              </span>
              <h3>{deal.title}</h3>
              <p>{deal.text}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
