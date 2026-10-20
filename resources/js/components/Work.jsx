import { useEffect, useRef, useState } from 'react';

// Our own product told as a short case study: the problem, what we built, what changed.
// The screenshots use made-up restaurant data, never a client's.
const OPSMASTER = 'https://opsmaster.tech';

const STORY = [
  { n: '١', title: 'المشكلة', text: 'مطاعم بعدة فروع تُدار بالواتساب والأوراق: لا يعرف المالك هل نُظّف الفرع، ولا كم بقي في المخزون، ولا هل ربح اليوم.' },
  { n: '٢', title: 'ما بنيناه', text: 'نظام سحابي واحد لمهام الفروع اليومية، والمخزون والمشتريات، والوصفات وتكلفتها، والإيرادات ونقطة التعادل.' },
  { n: '٣', title: 'ما تغيّر', text: 'كل فرع في شاشة واحدة: يسلّم الموظف مهامه من جواله بالصور، ويرى المالك أرقامه يوماً بيوم.' },
];

const SCREENS = [
  { src: 'ops-web-01-revenue.jpg', name: 'الإيرادات', alt: 'شاشة الإيرادات اليومية: إيراد الشهر، والإيراد يوماً بيوم، والتوزيع حسب طريقة الدفع' },
  { src: 'ops-web-02-menu.jpg', name: 'هندسة المينيو', alt: 'شاشة هندسة المينيو: الأطباق مصنّفة حسب الطلب والربح، مع تكلفة كل طبق' },
  { src: 'ops-web-03-purchasing.jpg', name: 'المشتريات', alt: 'شاشة طلبات الشراء: كل طلب ومرحلته، من اعتماد المشتريات إلى الاستلام والسداد' },
];

export default function Work() {
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
  const next = () => show((active + 1) % SCREENS.length);
  // Pressing play clears the hover hold too, or the show would stay still under the pointer.
  const toggle = () => { setStopped((v) => !v); setHeld(false); };

  return (
    <section className="block" id="work" aria-labelledby="work-title">
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">أعمالنا</span>
          <h2 id="work-title">منتج بنيناه ونشغّله بأنفسنا.</h2>
          <p>وبالدقة نفسها نبني مشاريع عملائنا.</p>
        </div>

        <div className="work-case">
          <div className="work-story">
            <h3><img src="/work/opsmaster-logo.svg" alt="OpsMaster" width="2929" height="574" /></h3>
            <ol>
              {STORY.map((s) => (
                <li key={s.n}>
                  <span className="ws-n" aria-hidden="true">{s.n}</span>
                  <div><b>{s.title}</b><p>{s.text}</p></div>
                </li>
              ))}
            </ol>
            <div className="work-btns">
              <a className="btn btn-gold" href={`${OPSMASTER}/demo`} target="_blank" rel="noopener">جرّب النظام</a>
              <a className="btn btn-line" href={OPSMASTER} target="_blank" rel="noopener">زيارة الموقع</a>
            </div>
          </div>

          <div className={'work-show' + (paused ? ' paused' : '')} ref={showRef}
            onPointerEnter={(e) => e.pointerType === 'mouse' && setHeld(true)} onPointerLeave={() => setHeld(false)}>
            <div className="work-frame">
              <div className="work-screen">
                {SCREENS.map((s, i) => (
                  <img key={s.src} className={i === active ? 'on' : ''} src={`/work/${s.src}`} alt={s.alt}
                    aria-hidden={i !== active} width="1600" height="900" loading="lazy" />
                ))}
              </div>
              <img className="work-phone" src="/work/ops-mobile-01-tasks.jpg" width="780" height="1688" loading="lazy"
                alt="شاشة «مهامي» على الجوال: مهام الموظف لهذا اليوم ونسبة إنجازها" />
            </div>

            <div className="work-dots">
              {SCREENS.map((s, i) => (
                <button key={s.src} type="button" aria-label={`اعرض شاشة ${s.name}`} aria-current={i === active ? 'true' : undefined}
                  className={i === active ? 'on' : i < active ? 'done' : ''} onClick={() => show(i)}>
                  {i === active && <i key={turn} onAnimationEnd={next} />}
                </button>
              ))}
              {/* Announced only once the show is stopped; a self-turning show would speak every few seconds. */}
              <span className="work-name" aria-live={stopped ? 'polite' : 'off'}>{SCREENS[active].name}</span>
              <button type="button" className="work-pause" onClick={toggle}>
                {stopped ? 'تشغيل' : 'إيقاف'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
