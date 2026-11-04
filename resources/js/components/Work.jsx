import WorkLogo from './WorkLogo';
import { useT } from '../lib/i18n';

// Our projects as a row of logos: one with a website of its own opens it, the others their page here.
export default function Work({ projects }) {
  const t = useT();

  return (
    <section className="block" id="work" aria-labelledby="work-title">
      <div className="wrap">
        <div className="sec-head">
          <h2 id="work-title">{t('landing.work.title')}</h2>
        </div>

        <ul className="work-logos">
          {projects.map((project) => {
            const name = t(`work.projects.${project.slug}.name`);

            return (
              <li key={project.slug}>
                <a className={`work-logo work-logo-${project.slug}`} href={project.href}
                  {...(project.external ? { target: '_blank', rel: 'noopener' } : {})}
                  aria-label={project.external ? t('landing.work.visit_site', { name }) : t('landing.work.open_project', { name })}>
                  <WorkLogo slug={project.slug} name={name} />
                </a>
              </li>
            );
          })}
        </ul>
      </div>
    </section>
  );
}
