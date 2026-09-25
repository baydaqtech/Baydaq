import { useEffect, useState } from 'react';
import Nav from '../components/Nav';
import Hero from '../components/Hero';
import TechStrip from '../components/TechStrip';
import Services from '../components/Services';
import CheckerRule from '../components/CheckerRule';
import Process from '../components/Process';
import BoardStory from '../components/BoardStory';
import About from '../components/About';
import ContactSection from '../components/ContactSection';
import Footer from '../components/Footer';
import { initPageMotion } from '../lib/pageMotion';
import { initChessGame } from '../lib/chessGame';

// The 3D board story runs whenever motion is allowed; reduced-motion
// visitors keep the flat services and process sections.
const STORY_3D = '(prefers-reduced-motion: no-preference)';

export default function Landing() {
  // Decided once, before the page scripts place floaters around these sections.
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
        <TechStrip />
        {story3d ? <BoardStory /> : (
          <>
            <Services />
            <CheckerRule />
            <Process />
          </>
        )}
        <CheckerRule />
        <About />
        <CheckerRule />
        <ContactSection />
      </main>
      <Footer />
    </>
  );
}
