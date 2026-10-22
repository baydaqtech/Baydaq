import { useEffect, useRef, useState } from 'react';
import { useCopy, useT } from '../lib/i18n';

// Our own product told as a short case study: the problem, what we built, what changed.
// The screenshots use made-up restaurant data, never a client's.
const OPSMASTER = 'https://opsmaster.tech';

// Screenshot files, in the order of the screen names and descriptions in lang/*/landing.php.
const SCREEN_FILES = ['ops-web-01-revenue.jpg', 'ops-web-02-menu.jpg', 'ops-web-03-purchasing.jpg'];

export default function Work() {
  const t = useT();
  const story = useCopy('landing.work.story');
  const screens = useCopy('landing.work.screens').map((screen, i) => ({ ...screen, src: SCREEN_FILES[i] }));
  const [active, setActive] = useState(0);
  // Bumped on every switch, so even re-choosing the current square restarts its fill.
  const [turn, setTurn] = useState(0);
  const [stopped, setStopped] = useState(false);
  const [held, setHeld] = useState(false);
  const [inView, setInView] = useState(false);
  const showRef = useRef(null);

  // The screens only turn while the visitor can see them.
  useEffect(() => {
    const io = new IntersectionObserver(([e]) => setInView(e.isIntersecting), { threshold: 0.4 });
    io.observe(showRef.current);
    return () => io.disconnect();
  }, []);

  // Each square's gold fill is the timer: when it finishes, the next screen comes in. Pausing the
  // fill (pause button, a mouse over the show, or off screen) pauses the turn with it. Only a mouse
  // holds it: a tap fires an enter with no leave, which would stop the show for good on phones.
  const paused = stopped || held || !inView;
  const show = (i) => { setActive(i); setTurn((n) => n + 1); };
  const next = () => show((active + 1) % screens.length);
  // Pressing play clears the hover hold too, or the show would stay still under the pointer.
  const toggle = () => { setStopped((v) => !v); setHeld(false); };

  return (
    <section className="block" id="work" aria-labelledby="work-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.work.eyebrow')}</span>
          <h2 id="work-title">{t('landing.work.title')}</h2>
          <p>{t('landing.work.lede')}</p>
        </div>

        <div className="work-case">
          <div className="work-story">
            <h3><img src="/work/opsmaster-logo.svg" alt="OpsMaster" width="2929" height="574" /></h3>
            <ol>
              {story.map((s) => (
                <li key={s.n}>
                  <span className="ws-n" aria-hidden="true">{s.n}</span>
                  <div><b>{s.title}</b><p>{s.text}</p></div>
                </li>
              ))}
            </ol>
            <div className="work-btns">
              <a className="btn btn-gold" href={`${OPSMASTER}/demo`} target="_blank" rel="noopener">{t('landing.work.try')}</a>
              <a className="btn btn-line" href={OPSMASTER} target="_blank" rel="noopener">{t('landing.work.visit')}</a>
            </div>
          </div>

          <div className={'work-show' + (paused ? ' paused' : '')} ref={showRef}
            onPointerEnter={(e) => e.pointerType === 'mouse' && setHeld(true)} onPointerLeave={() => setHeld(false)}>
            <div className="work-frame">
              <div className="work-screen">
                {screens.map((s, i) => (
                  <img key={s.src} className={i === active ? 'on' : ''} src={`/work/${s.src}`} alt={s.alt}
                    aria-hidden={i !== active} width="1600" height="900" loading="lazy" />
                ))}
              </div>
              <img className="work-phone" src="/work/ops-mobile-01-tasks.jpg" width="780" height="1688" loading="lazy"
                alt={t('landing.work.phone_alt')} />
            </div>

            <div className="work-dots">
              {screens.map((s, i) => (
                <button key={s.src} type="button" aria-label={t('landing.work.show_screen', { name: s.name })} aria-current={i === active ? 'true' : undefined}
                  className={i === active ? 'on' : i < active ? 'done' : ''} onClick={() => show(i)}>
                  {i === active && <i key={turn} onAnimationEnd={next} />}
                </button>
              ))}
              {/* Announced only once the show is stopped; a self-turning show would speak every few seconds. */}
              <span className="work-name" aria-live={stopped ? 'polite' : 'off'}>{screens[active].name}</span>
              <button type="button" className="work-pause" onClick={toggle}>
                {stopped ? t('landing.work.play') : t('landing.work.pause')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
