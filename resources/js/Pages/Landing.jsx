import { useEffect, useState } from 'react';
import Nav from '../components/Nav';
import Hero from '../components/Hero';
import TechStrip from '../components/TechStrip';
import Services from '../components/Services';
import CheckerRule from '../components/CheckerRule';
import Process from '../components/Process';
import BoardStory from '../components/BoardStory';
import About, { ABOUT_IN_CONTACT } from '../components/About';
import ContactSection from '../components/ContactSection';
import Footer from '../components/Footer';
import { initPageMotion } from '../lib/pageMotion';
import { initChessGame } from '../lib/chessGame';

// The 3D board story is for wide screens with motion allowed; phones and
// reduced-motion visitors keep the flat services and process sections.
const STORY_3D = '(min-width: 901px) and (prefers-reduced-motion: no-preference)';

// The "technologies we use" logo strip under the hero is hidden for now; set to true to show it.
const SHOW_TECH_STRIP = false;

export default function Landing() {
  // Decided once, before the page scripts place board patches around these sections.
  const [story3d] = useState(() => window.matchMedia(STORY_3D).matches);

  useEffect(() => {
    // The prototype's own scripts, started once the markup is on the page.
    initPageMotion();
    initChessGame();
  }, []);

  return (
    <>
      <Nav />
      <main id="top">
        <Hero />
        {SHOW_TECH_STRIP && <TechStrip />}
        {story3d ? <BoardStory /> : (
          <>
            <Services />
            <CheckerRule />
            <Process />
          </>
        )}
        {!ABOUT_IN_CONTACT && (
          <>
            <CheckerRule />
            <About />
          </>
        )}
        <CheckerRule />
        <ContactSection />
      </main>
      <Footer />
    </>
  );
}
