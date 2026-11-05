import { useEffect, useState } from 'react';
import Nav from '../components/Nav';
import Hero from '../components/Hero';
import TechStrip from '../components/TechStrip';
import Deals from '../components/Deals';
import Services from '../components/Services';
import CheckerRule from '../components/CheckerRule';
import Process from '../components/Process';
import BoardStory from '../components/BoardStory';
import ProductsSection from '../components/ProductsSection';
import PlatformSection from '../components/PlatformSection';
import Work from '../components/Work';
import About, { ABOUT_IN_CONTACT } from '../components/About';
import GetStarted from '../components/GetStarted';
import ContactSection from '../components/ContactSection';
import Footer from '../components/Footer';
import { useCopy } from '../lib/i18n';
import { initPageMotion } from '../lib/pageMotion';
import { initChessGame } from '../lib/chessGame';

// The 3D board story is for wide screens with motion allowed; phones and
// reduced-motion visitors keep the flat services and process sections.
const STORY_3D = '(min-width: 901px) and (prefers-reduced-motion: no-preference)';

// The "technologies we use" logo strip under the hero is hidden for now; set to true to show it.
const SHOW_TECH_STRIP = false;

// The "what we do" section ("three ways to work with us") is hidden for now; set to true to show it.
const SHOW_DEALS = false;

// The "products we build and run" and "ready services for your app" sections are hidden for now; set to true to
// show them. Their full lists stay on /products and /services.
const SHOW_PRODUCTS = false;
const SHOW_PLATFORM_SERVICES = false;

export default function Landing({ products, services, work }) {
  // Decided once, before the page scripts place board patches around these sections.
  const [story3d] = useState(() => window.matchMedia(STORY_3D).matches);
  const chessText = useCopy('landing.chess');

  useEffect(() => {
    // The prototype's own scripts, started once the markup is on the page.
    initPageMotion();
    initChessGame(chessText);
  }, []);

  return (
    <>
      <Nav />
      <main id="top">
        <Hero />
        {SHOW_TECH_STRIP && <TechStrip />}
        {SHOW_DEALS && (
          <>
            <Deals />
            <CheckerRule />
          </>
        )}
        {story3d ? <BoardStory /> : (
          <>
            <Services />
            <CheckerRule />
            <Process />
          </>
        )}
        <CheckerRule />
        {SHOW_PRODUCTS && <ProductsSection products={products} />}
        {SHOW_PLATFORM_SERVICES && <PlatformSection services={services} />}
        {(SHOW_PRODUCTS || SHOW_PLATFORM_SERVICES) && <CheckerRule />}
        <Work projects={work} />
        {!ABOUT_IN_CONTACT && (
          <>
            <CheckerRule />
            <About />
          </>
        )}
        <CheckerRule />
        <GetStarted />
        <ContactSection />
      </main>
      <Footer />
    </>
  );
}
