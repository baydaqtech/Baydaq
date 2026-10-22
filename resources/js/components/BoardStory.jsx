import { useEffect, useRef, useState } from 'react';
import { useT } from '../lib/i18n';
import { PIECES, useServiceCards } from './Services';
import { useSteps } from './Process';

// Services + process told on one 3D board that follows the scroll (desktop only).
// Stages 0–3: one service piece at a time rises and shows its moves.
// Stages 4–7: the logo pawn walks e2 → e5.

// [file, rank] from a1 = [0, 0].
const SPOTS = { queen: [3, 3], knight: [5, 5], rook: [1, 6], bishop: [6, 1] };
const PAWN_PATH = [[4, 1], [4, 2], [4, 3], [4, 4]];

// One stage per service piece, then one per pawn step (one per process step).
const SERVICE_STAGES = PIECES.length;
const STAGES = SERVICE_STAGES + PAWN_PATH.length;

// Camera per stage: [tilt, turn] in degrees; the scroll blends between neighbours.
// The tilt stays put and the turn only ever goes one way. Steps 1–3 keep a clearly visible
// turn (a few degrees reads as square under this tilt); only the last step squares the board
// with the page, still in 3D.
const CAMERA = [[58, -46], [58, -38], [58, -30], [58, -22], [58, -19], [58, -16], [58, -13], [58, 0]];

const LINES = { rook: [[1, 0], [-1, 0], [0, 1], [0, -1]], bishop: [[1, 1], [1, -1], [-1, 1], [-1, -1]] };
LINES.queen = LINES.rook.concat(LINES.bishop);
const JUMPS = [[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]];

// Squares each service piece can reach on an otherwise empty board. [file, rank, distance]
function movesFor(piece) {
  const [c, r] = SPOTS[piece];
  const free = (x, y) => x >= 0 && x < 8 && y >= 0 && y < 8;
  if (piece === 'knight') {
    return JUMPS.map(([dc, dr]) => [c + dc, r + dr, 1]).filter(([x, y]) => free(x, y));
  }
  const out = [];
  LINES[piece].forEach(([dc, dr]) => {
    for (let k = 1; free(c + dc * k, r + dr * k); k++) out.push([c + dc * k, r + dr * k, k]);
  });
  return out;
}
const MOVES = Object.fromEntries(PIECES.map((piece) => [piece, movesFor(piece)]));

const at = ([c, r], extra) => ({ '--c': c, '--r': r, ...extra });
const smooth = (t) => t * t * (3 - 2 * t);

export default function BoardStory() {
  const t = useT();
  const services = useServiceCards();
  const steps = useSteps();
  const secRef = useRef(null);
  const stageRef = useRef(null);
  const sceneRef = useRef(null);
  const boardRef = useRef(null);
  const [stage, setStage] = useState(0);
  // Pieces stay down until the board is on screen, so the first one rises in view (and again on return).
  const [entered, setEntered] = useState(false);

  useEffect(() => {
    const sec = secRef.current, box = stageRef.current, scene = sceneRef.current, board = boardRef.current;
    // The angles go straight onto the board and the five standing pieces. Inherited CSS variables
    // would restyle every square and dot on each scroll frame, which tablets can't keep up with.
    const figs = board.querySelectorAll('.p3-fig');
    let raf = 0, tiltX = 0, tiltY = 0;

    function update() {
      raf = 0;
      const run = sec.offsetHeight - box.offsetHeight;
      const top = parseFloat(getComputedStyle(box).top) || 0;
      const p = Math.min(1, Math.max(0, (top - sec.getBoundingClientRect().top) / run));
      setStage(Math.min(STAGES - 1, Math.floor(p * STAGES)));

      const t = Math.min(STAGES - 1, Math.max(0, p * STAGES - 0.5));
      const i = Math.floor(t), f = smooth(t - i), j = Math.min(STAGES - 1, i + 1);
      // The pointer's sideways turn fades out over the last step, so the board ends square with the page.
      const turnLean = 1 - Math.min(1, Math.max(0, t - (STAGES - 2)));
      const rx = CAMERA[i][0] + (CAMERA[j][0] - CAMERA[i][0]) * f + tiltY;
      const rz = CAMERA[i][1] + (CAMERA[j][1] - CAMERA[i][1]) * f + tiltX * turnLean;
      board.style.transform = `rotateX(${rx.toFixed(2)}deg) rotateZ(${rz.toFixed(2)}deg)`;
      const stand = `rotateZ(${(-rz).toFixed(2)}deg) rotateX(${(-rx).toFixed(2)}deg)`;
      figs.forEach((fig) => { fig.style.transform = stand; });
    }
    function queue() { if (!raf) raf = requestAnimationFrame(update); }

    // The board leans a little toward the pointer.
    function onPointer(e) {
      if (e.pointerType !== 'mouse') return;
      const r = scene.getBoundingClientRect();
      tiltX = ((e.clientX - r.left) / r.width - 0.5) * 8;
      tiltY = ((e.clientY - r.top) / r.height - 0.5) * -5;
      queue();
    }
    function onLeave() { tiltX = tiltY = 0; queue(); }

    const seen = new IntersectionObserver(([e]) => setEntered(e.isIntersecting), { threshold: 0.45 });
    seen.observe(scene);

    update();
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    scene.addEventListener('pointermove', onPointer);
    scene.addEventListener('pointerleave', onLeave);
    return () => {
      seen.disconnect();
      cancelAnimationFrame(raf);
      window.removeEventListener('scroll', queue);
      window.removeEventListener('resize', queue);
      scene.removeEventListener('pointermove', onPointer);
      scene.removeEventListener('pointerleave', onLeave);
    };
  }, []);

  // Jump to the middle of a stage's scroll range.
  function go(i) {
    const sec = secRef.current, box = stageRef.current;
    const run = sec.offsetHeight - box.offsetHeight;
    const top = parseFloat(getComputedStyle(box).top) || 0;
    const secTop = sec.getBoundingClientRect().top + window.scrollY;
    window.scrollTo({ top: secTop - top + ((i + 0.5) / STAGES) * run, behavior: 'smooth' });
  }

  const inServices = stage < SERVICE_STAGES;
  const step = stage - SERVICE_STAGES;
  const pawnSquare = PAWN_PATH[Math.max(0, step)];
  const onBoard = (i) => entered && stage === i;

  return (
    <section className="block story" id="services" ref={secRef} aria-labelledby="services-title" style={{ '--stages': STAGES }}>
      <span className="story-anchor" id="process" aria-hidden="true" />
      <div className="story-stage" ref={stageRef}>
        <div className="wrap story-grid">
          <div className="story-text">
            <div className="story-stack">
              <header className={'story-head' + (inServices ? ' on' : '')}>
                <span className="eyebrow">{t('landing.services_head.eyebrow')}</span>
                <h2 id="services-title">{t('landing.services_head.title')}</h2>
                <p>{t('landing.services_head.lede')}</p>
              </header>
              <header className={'story-head' + (inServices ? '' : ' on')}>
                <span className="eyebrow">{t('landing.process_head.eyebrow')}</span>
                <h2 id="process-title">{t('landing.process_head.title')}</h2>
                <p>{t('landing.process_head.lede')}</p>
              </header>
            </div>

            <div className="story-stack">
              {services.map((s, i) => (
                <article className={'story-card' + (stage === i ? ' on' : '')} key={s.piece}>
                  <span className="who mono">{s.tag}</span>
                  <h3>{s.title}</h3>
                  <p>{s.desc}</p>
                  <ul className="tags">
                    {s.tags.map((tag) => <li key={tag}>{tag}</li>)}
                  </ul>
                </article>
              ))}
              {steps.map((s, i) => (
                <article className={'story-card step' + (step === i ? ' on' : '')} key={s.coord}>
                  <div className="step-top"><span className="step-num">{s.n}</span><span className="step-phase">{s.phase}</span></div>
                  <h3>{s.title}</h3>
                  <p>{s.desc}</p>
                  <p className="step-get">{s.get}</p>
                </article>
              ))}
            </div>

            <div className="story-progress">
              <div>
                <span className="lbl">{t('landing.story.services')}</span>
                {services.map((s, i) => (
                  <button type="button" key={s.piece} aria-label={s.title} aria-current={stage === i ? 'step' : undefined}
                    className={stage === i ? 'on' : stage > i ? 'done' : ''} onClick={() => go(i)} />
                ))}
              </div>
              <div>
                <span className="lbl">{t('landing.story.steps')}</span>
                {steps.map((s, i) => (
                  <button type="button" key={s.coord} aria-label={s.title} aria-current={step === i ? 'step' : undefined}
                    className={step === i ? 'on' : step > i ? 'done' : ''} onClick={() => go(SERVICE_STAGES + i)} />
                ))}
              </div>
            </div>
          </div>

          <div className="story-scene" ref={sceneRef} aria-hidden="true">
            <span className="b3-floor" />
            <div className="b3" ref={boardRef}>
              <span className="b3-frame" />
              <span className="b3-edge n" /><span className="b3-edge s" /><span className="b3-edge e" /><span className="b3-edge w" />
              <span className="b3-grid" />

              {/* All square marks share one flat layer just above the squares, and the step
                  numbers one more above the pawn's shadow: three layers instead of one per mark. */}
              <div className="b3-marks">
                {services.map((s, i) => (
                  <span key={s.piece} className={'hl3 at' + (onBoard(i) ? ' on' : '')} style={at(SPOTS[s.piece])} />
                ))}
                {services.map((s, i) => MOVES[s.piece].map(([x, y, d]) => (
                  <span key={s.piece + x + y} className={'dot3 at' + (onBoard(i) ? ' on' : '')} style={at([x, y], { '--dl': d * 0.06 + 's' })} />
                )))}
                {PAWN_PATH.map((sq, i) => (
                  <span key={i} className={'trail3 at' + (step >= i ? ' on' : '') + (step === i ? ' now' : '')} style={at(sq)} />
                ))}
              </div>
              <div className="b3-nums">
                {PAWN_PATH.map((sq, i) => (
                  <b key={i} className={'at' + (step >= i ? ' on' : '') + (step === i ? ' now' : '')} style={at(sq)}>{steps[i].n}</b>
                ))}
              </div>

              {services.map((s, i) => (
                <span key={s.piece} className={'p3 at' + (onBoard(i) ? ' up act' : '')} style={at(SPOTS[s.piece])}>
                  <span className="p3-shadow" />
                  <span className="p3-fig"><span className="p3-body"><svg viewBox="0 0 45 45"><use href={`#p-${s.piece}`} /></svg></span></span>
                </span>
              ))}
              <span className={'p3 pawn at' + (inServices ? '' : ' up act')} style={at(pawnSquare)}>
                <span className="p3-shadow" />
                <span className="p3-fig"><span className="p3-body"><svg viewBox="0 0 100 100"><use href="#logo-mark" /></svg></span></span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
