import { Head } from '@inertiajs/react';
import Nav from './Nav';
import Footer from './Footer';
import CheckerRule from './CheckerRule';

/** Frame for the public pages other than the landing: nav, a page heading, content, footer. */
export default function SitePage({ title, eyebrow, lede, children }) {
  return (
    <>
      <Head title={title} />
      <Nav />
      <main id="top" className="site-page">
        <section className="block page-head">
          <div className="wrap">
            <div className="sec-head">
              {eyebrow && <span className="eyebrow">{eyebrow}</span>}
              <h1>{title}</h1>
              {lede && <p>{lede}</p>}
            </div>
            {children}
          </div>
        </section>
        <CheckerRule />
      </main>
      <Footer />
    </>
  );
}
