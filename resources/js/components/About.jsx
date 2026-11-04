import { useCopy, useT } from '../lib/i18n';

// Chess lines in this section (the Ruqaa headline, the pawn caption, the piece tags) are hidden
// for now but kept; set to true to show them again.
const SHOW_CHESS_LINES = false;
// The pawn-to-vizier drawing beside the copy and the three numbered principles under it are
// hidden for now too; set either to true to bring it back.
const SHOW_PAWN_FIGURE = false;
const SHOW_PRINCIPLES = false;

// "About" is folded into the top of the contact section for now, to shorten the page.
// Set to false to bring back the separate "about" section.
export const ABOUT_IN_CONTACT = true;

// Pieces for the three principles, in the order of landing.about.principles.
const PRINCIPLE_PIECES = ['knight', 'pawn', 'rook'];

export default function About() {
  const t = useT();
  const principles = useCopy('landing.about.principles').map((p, i) => ({ ...p, piece: PRINCIPLE_PIECES[i] }));
  return (
    <section className="block heritage" id="about" aria-labelledby="about-title" style={{ paddingTop: 0 }}>
      <div className={'wrap about-grid' + (SHOW_PAWN_FIGURE ? '' : ' solo')}>
        <div className="heritage-copy">
          <span className="eyebrow" id="about-title">{t('landing.about.eyebrow')}</span>
          {SHOW_CHESS_LINES && <p className="ruqaa gold">{t('landing.about.ruqaa')}</p>}
          <p>{t('landing.about.text')}</p>
        </div>

        {SHOW_PAWN_FIGURE && (
          <figure className="promo" aria-label={t('landing.about.figure_label')}>
            <div className="trail-row" aria-hidden="true">
              {[0, 1, 2, 3, 4, 5, 6].map((i) => <span className="t" key={i} />)}
              <span className="trail" />
              <span className="p ghost"><svg viewBox="0 0 45 45"><use href="#p-pawn" /></svg></span>
              <span className="p walker"><svg viewBox="0 0 45 45"><use href="#p-pawn" /></svg></span>
              <span className="p queen"><svg viewBox="0 0 45 45"><use href="#p-queen" /></svg></span>
            </div>
            <div className="trail-labels mono" aria-hidden="true">
              {['e2', 'e3', 'e4', 'e5', 'e6', 'e7', 'e8=Q'].map((l) => <span key={l}>{l}</span>)}
            </div>
            {SHOW_CHESS_LINES && <figcaption className="trail-cap">{t('landing.about.figure_caption')}</figcaption>}
          </figure>
        )}
      </div>

      {SHOW_PRINCIPLES && (
        <div className="wrap">
          <ul className="principles">
            {principles.map((p) => (
              <li key={p.piece}>
                <div className="pr-stage" aria-hidden="true">
                  <span className="pr-num">{p.n}</span>
                  <span className="pr-base" />
                  <svg className="pr-piece" viewBox="0 0 45 45"><use href={`#p-${p.piece}`} /></svg>
                </div>
                {SHOW_CHESS_LINES && <span className="pr-tag">{p.tag}</span>}
                <h3>{p.title}</h3>
                <p>{p.desc}</p>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}
