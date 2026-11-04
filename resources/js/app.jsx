import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  // Pages load on demand, so the landing page doesn't ship the platform UI and vice versa.
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.{jsx,tsx}');
    return (pages[`./Pages/${name}.tsx`] ?? pages[`./Pages/${name}.jsx`])();
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
