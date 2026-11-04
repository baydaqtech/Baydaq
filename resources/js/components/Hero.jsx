import { useCopy, useT } from '../lib/i18n';

// The moves log under the board is hidden for now; set to true to show it again.
const SHOW_MOVES_LOG = false;

export default function Hero() {
  const t = useT();
  const foot = useCopy('landing.hero.foot');

  return (
    <section className="hero" aria-labelledby="hero-title">
      <div className="wrap">
        <div className="hero-copy">
          <span className="chip"><i />{t('landing.hero.chip')}</span>
          <h1 id="hero-title">
            <span className="type1">{t('landing.hero.title_1')}</span><br /><span className="gold">{t('landing.hero.title_2')}</span>
          </h1>
          <p className="lede">{t('landing.hero.lede')}</p>
          <div className="actions">
            <a className="btn btn-gold" href="/request/partnership">{t('landing.hero.cta_partnership')} <span className="arr" aria-hidden="true">←</span></a>
            <a className="btn btn-line" href="/request/service">{t('landing.hero.cta_service')}</a>
          </div>
          <p className="hero-login">
            {t('landing.hero.have_account')} <a href="/login">{t('landing.hero.login')}</a>
          </p>
          <ul className="hero-foot">
            {foot.map((line) => <li key={line}><b>✓</b> {line}</li>)}
          </ul>
        </div>

        <div className="game">
          <div className="board-frame">
            <div className="board" id="board" role="group" aria-label={t('landing.hero.board_label')} />
            {/* After a few moves (or when the game ends) this points the visitor back to what we do. Shown by chessGame.js. */}
            <div className="invite" id="invite" role="dialog" aria-modal="false" aria-labelledby="invite-title" hidden>
              <div className="invite-card">
                <p className="invite-title" id="invite-title">{t('landing.hero.invite_title')}</p>
                <p className="invite-text">{t('landing.hero.invite_text')}</p>
                <div className="invite-btns">
                  <a className="btn btn-gold" href="#services" id="invite-go">{t('landing.hero.invite_go')} <span aria-hidden="true">↓</span></a>
                  <button className="btn btn-line" type="button" id="invite-close">{t('landing.hero.invite_close')}</button>
                </div>
              </div>
            </div>
          </div>
          <div className="game-bar">
            <p className="status" id="status" role="status" aria-live="polite">
              <i className="w" /><span>{t('landing.hero.status_start')}</span>
            </p>
            <div className="game-btns">
              <button className="btn btn-line btn-sm" id="undo" type="button">{t('landing.hero.undo')}</button>
              <button className="btn btn-line btn-sm" id="reset" type="button">{t('landing.hero.reset')}</button>
            </div>
          </div>
          {SHOW_MOVES_LOG && <ol className="moves-log" id="moves" aria-label={t('landing.hero.moves_label')} data-empty={t('landing.hero.moves_empty')} />}
        </div>
      </div>
      {/* Nudges visitors (and players) on to the services; fades once the page is scrolled. */}
      <a className="scroll-cue" href="#services">
        <span className="cue-text">{t('landing.hero.cue')}</span>
        <span className="cue-dot" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path className="c1" d="M7 7l5 5 5-5" /><path className="c2" d="M7 13l5 5 5-5" /></svg>
        </span>
      </a>
    </section>
  );
}
