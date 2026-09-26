import { useEffect, useRef, useState } from 'react';
import { SERVICES } from './Services';
import { STEPS } from './Process';

// Services + process told on one 3D board that follows the scroll (desktop only).
// Stages 0–3: one service piece at a time rises and shows its moves.
// Stages 4–7: the logo pawn walks e2 → e5.
const STAGES = SERVICES.length + STEPS.length;
const SERVICE_STAGES = SERVICES.length;

// [file, rank] from a1 = [0, 0].
const SPOTS = { queen: [3, 3], knight: [5, 5], rook: [1, 6], bishop: [6, 1] };
const PAWN_PATH = [[4, 1], [4, 2], [4, 3], [4, 4]];

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
const MOVES = Object.fromEntries(SERVICES.map((s) => [s.piece, movesFor(s.piece)]));

const at = ([c, r], extra) => ({ '--c': c, '--r': r, ...extra });
const smooth = (t) => t * t * (3 - 2 * t);

export default function BoardStory() {
  const secRef = useRef(null);
  const stageRef = useRef(null);
  const sceneRef = useRef(null);
  const [stage, setStage] = useState(0);
  // Pieces stay down until the board is on screen, so the first one rises in view (and again on return).
  const [entered, setEntered] = useState(false);

  useEffect(() => {
    const sec = secRef.current, box = stageRef.current, scene = sceneRef.current;
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
      scene.style.setProperty('--rx', rx.toFixed(2) + 'deg');
      scene.style.setProperty('--rz', rz.toFixed(2) + 'deg');
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
                <span className="eyebrow">خدماتنا</span>
                <h2 id="services-title">ماذا نقدّم لك؟</h2>
                <p>أربع خدمات تغطّي ما تحتاجه شركتك تقنياً، من الفكرة إلى التشغيل.</p>
              </header>
              <header className={'story-head' + (inServices ? '' : ' on')}>
                <span className="eyebrow">كيف نعمل</span>
                <h2 id="process-title">أربع خطوات واضحة.</h2>
                <p>تعرف في كل خطوة ماذا نفعل، وتستلم في نهايتها شيئاً تراه بعينك.</p>
              </header>
            </div>

            <div className="story-stack">
              {SERVICES.map((s, i) => (
                <article className={'story-card' + (stage === i ? ' on' : '')} key={s.piece}>
                  <span className="who mono">{s.tag}</span>
                  <h3>{s.title}</h3>
                  <p>{s.desc}</p>
                  <ul className="tags">
                    {s.tags.map((t) => <li key={t}>{t}</li>)}
                  </ul>
                </article>
              ))}
              {STEPS.map((s, i) => (
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
                <span className="lbl">الخدمات</span>
                {SERVICES.map((s, i) => (
                  <button type="button" key={s.piece} aria-label={s.title} aria-current={stage === i ? 'step' : undefined}
                    className={stage === i ? 'on' : stage > i ? 'done' : ''} onClick={() => go(i)} />
                ))}
              </div>
              <div>
                <span className="lbl">الخطوات</span>
                {STEPS.map((s, i) => (
                  <button type="button" key={s.coord} aria-label={s.title} aria-current={step === i ? 'step' : undefined}
                    className={step === i ? 'on' : step > i ? 'done' : ''} onClick={() => go(SERVICE_STAGES + i)} />
                ))}
              </div>
            </div>
          </div>

          <div className="story-scene" ref={sceneRef} aria-hidden="true">
            <span className="b3-floor" />
            <div className="b3">
              <span className="b3-frame" />
              <span className="b3-edge n" /><span className="b3-edge s" /><span className="b3-edge e" /><span className="b3-edge w" />
              <div className="b3-grid">
                {Array.from({ length: 64 }, (_, k) => {
                  const c = k % 8, r = 7 - Math.floor(k / 8);
                  return <span key={k} className={'c3' + ((c + r) % 2 === 0 ? ' d' : '')} />;
                })}
              </div>

              {SERVICES.map((s, i) => (
                <span key={s.piece} className={'hl3 at' + (onBoard(i) ? ' on' : '')} style={at(SPOTS[s.piece])} />
              ))}
              {SERVICES.map((s, i) => MOVES[s.piece].map(([x, y, d]) => (
                <span key={s.piece + x + y} className={'dot3 at' + (onBoard(i) ? ' on' : '')} style={at([x, y], { '--dl': d * 0.06 + 's' })} />
              )))}
              {PAWN_PATH.map((sq, i) => (
                <span key={i} className={'trail3 at' + (step >= i ? ' on' : '') + (step === i ? ' now' : '')} style={at(sq)}>
                  <b>{STEPS[i].n}</b>
                </span>
              ))}

              {SERVICES.map((s, i) => (
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
