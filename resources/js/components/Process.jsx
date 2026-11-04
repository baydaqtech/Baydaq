import { useCopy, useT } from '../lib/i18n';

// The four steps sit on the first rank, e1 to h1.
const COORDS = ['e1', 'f1', 'g1', 'h1'];

export function useSteps() {
  return useCopy('landing.steps').map((step, i) => ({ ...step, coord: COORDS[i] }));
}

export default function Process() {
  const t = useT();
  const steps = useSteps();

  return (
    <section className="block" id="process" aria-labelledby="process-title" style={{ paddingTop: 0 }}>
      <div className="wrap">
        <div className="sec-head">
          <span className="eyebrow">{t('landing.process_head.eyebrow')}</span>
          <h2 id="process-title">{t('landing.process_head.title')}</h2>
          <p>{t('landing.process_head.lede')}</p>
        </div>

        <ol className="rank">
          {steps.map((s) => (
            <li className="sq" key={s.coord}>
              <div className="top"><span className="num">{s.n}</span><span className="phase">{s.phase}</span></div>
              <div>
                <h3>{s.title}</h3>
                <p>{s.desc}</p>
                <p className="get">{s.get}</p>
              </div>
              <span className="coord mono">{s.coord}</span>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
