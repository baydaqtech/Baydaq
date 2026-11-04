import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Footer from '../components/Footer';
import Nav from '../components/Nav';
import WorkLogo from '../components/WorkLogo';
import { useCopy, useLocale, useT } from '../lib/i18n';

const twoDigits = (number) => String(number).padStart(2, '0');

/** A web screen in a browser window. */
function Browser({ screen, lazy = false }) {
  return (
    <div className="project-browser">
      <span className="project-browser-bar" aria-hidden="true"><i /><i /><i /></span>
      <img src={screen.src} alt={screen.caption} loading={lazy ? 'lazy' : undefined} />
    </div>
  );
}

/** The web screens: one large, the others as thumbnails to switch to; each opens full size. */
function WebGallery({ screens, w, back, forward }) {
  const [current, setCurrent] = useState(0);
  const screen = screens[current];
  const go = (step) => setCurrent((current + step + screens.length) % screens.length);

  return (
    <div className="project-gallery-web">
      <figure className="project-stage">
        <Browser key={screen.src} screen={screen} />
        <figcaption>
          <span className="project-stage-count" dir="ltr">{twoDigits(current + 1)} / {twoDigits(screens.length)}</span>
          <span className="project-stage-caption" aria-live="polite">{screen.caption}</span>
          <span className="project-stage-actions">
            {screens.length > 1 && (
              <>
                <button type="button" onClick={() => go(-1)} aria-label={w('previous_screen')}>{back}</button>
                <button type="button" onClick={() => go(1)} aria-label={w('next_screen')}>{forward}</button>
              </>
            )}
            <a href={screen.src} target="_blank" rel="noopener">{w('open_full')} <span aria-hidden="true">↗</span></a>
          </span>
        </figcaption>
      </figure>

      {screens.length > 1 && (
        <ul className="project-thumbs">
          {screens.map((item, at) => (
            <li key={item.src}>
              <button type="button" aria-pressed={at === current} aria-label={w('show_screen', { name: item.caption })}
                onClick={() => setCurrent(at)}>
                <img src={item.src} alt="" loading="lazy" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/**
 * One of our projects as a case study: what it is with the product beside it, its story and architecture, its
 * screens on a dark board, what is inside, and the projects around it.
 */
export default function WorkProject({ slug, web, mobile, position, previous, next }) {
  const t = useT();
  const { dir } = useLocale();
  const project = useCopy(`work.projects.${slug}`);
  const w = (key, replace) => t(`work.ui.${key}`, replace);
  const name = (other) => t(`work.projects.${other}.name`);
  // Arrows follow the reading direction: "back" points to the start of the line.
  const [back, forward] = dir === 'rtl' ? ['→', '←'] : ['←', '→'];

  return (
    <>
      <Head title={`${project.name}: ${project.tagline}`} />
      <Nav />
      <main id="top" className={`site-page project-page project-${slug}`}>
        <section className="project-hero">
          <div className="wrap">
            <nav className="project-crumbs" aria-label={w('all')}>
              <a href="/#work"><span aria-hidden="true">{back}</span> {w('all')}</a>
              <span>{w('position', { index: position.index, total: position.total })}</span>
            </nav>

            <div className="project-hero-grid">
              <div className="project-hero-copy">
                <WorkLogo slug={slug} name={project.name} />
                <p className="project-kicker">{project.tags.join(' · ')}</p>
                <h1>{project.tagline}</h1>
                <p className="project-lead">{project.summary}</p>
                <dl className="project-stats" aria-label={w('numbers')}>
                  {project.numbers.map((number) => (
                    <div key={number.label}>
                      <dt>{number.label}</dt>
                      <dd dir="ltr">{number.value}</dd>
                    </div>
                  ))}
                </dl>
              </div>

              {web[0] && (
                <div className="project-showcase">
                  <Browser screen={web[0]} />
                  {mobile[0] && (
                    <div className="project-phone project-showcase-phone">
                      <img src={mobile[0].src} alt={mobile[0].caption} />
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </section>

        <section className="block project-story">
          <div className="wrap project-story-grid">
            <div className="project-about">
              <span className="eyebrow">{w('overview')}</span>
              <p>{project.overview}</p>
              {project.highlight && (
                <aside className="project-highlight">
                  <b>{w('highlight')}</b>
                  <p>{project.highlight}</p>
                </aside>
              )}
            </div>

            <div className="project-stack">
              <h2>{w('architecture')}</h2>
              <ol>
                {project.architecture.map((part, at) => (
                  <li key={part.name}>
                    <span className="project-stack-index" dir="ltr" aria-hidden="true">{twoDigits(at + 1)}</span>
                    <b>{part.name}</b>
                    <span>{part.stack}</span>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </section>

        <section className="project-gallery" aria-labelledby="project-screens">
          <div className="wrap">
            <h2 id="project-screens">{w('screens')}</h2>
            {web.length > 0 && (
              <>
                <h3>{w('web')}</h3>
                <WebGallery screens={web} w={w} back={back} forward={forward} />
              </>
            )}
            {mobile.length > 0 && (
              <>
                <h3>{w('mobile')}</h3>
                <ul className="project-phones">
                  {mobile.map((screen) => (
                    <li key={screen.src}>
                      <a className="project-phone" href={screen.src} target="_blank" rel="noopener"
                        aria-label={w('open_image', { name: screen.caption })}>
                        <img src={screen.src} alt={screen.caption} loading="lazy" />
                      </a>
                      <span>{screen.caption}</span>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </div>
        </section>

        <section className="block">
          <div className="wrap">
            <div className="sec-head"><h2>{w('specs')}</h2></div>
            <ul className="project-features">
              {project.specs.map((spec, at) => (
                <li key={spec.name}>
                  <span className="project-feature-index" dir="ltr" aria-hidden="true">{twoDigits(at + 1)}</span>
                  <h3>{spec.name}</h3>
                  <p>{spec.text}</p>
                </li>
              ))}
            </ul>

            {(previous || next) && (
              <nav className="project-pager" aria-label={w('all')}>
                {previous && (
                  <a href={`/work/${previous}`}>
                    <small><span aria-hidden="true">{back}</span> {w('previous')}</small>
                    <WorkLogo slug={previous} name={name(previous)} />
                  </a>
                )}
                {next && (
                  <a className="project-pager-next" href={`/work/${next}`}>
                    <small>{w('next')} <span aria-hidden="true">{forward}</span></small>
                    <WorkLogo slug={next} name={name(next)} />
                  </a>
                )}
              </nav>
            )}

            <div className="page-cta">
              <div>
                <h2>{w('cta_title')}</h2>
                <p>{w('cta_text')}</p>
              </div>
              <div className="page-cta-btns">
                <a className="btn btn-gold" href="/request/service">{t('landing.hero.cta_service')}</a>
                <a className="btn btn-line" href="/#contact">{t('landing.nav.contact')}</a>
              </div>
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
