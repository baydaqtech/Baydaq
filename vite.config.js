import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
  // VITE_PORT in .env keeps the dev server off ports other local projects use.
  const port = Number(loadEnv(mode, process.cwd(), '').VITE_PORT || 5173);

  return {
    plugins: [
      laravel({
        input: ['resources/css/app.css', 'resources/css/platform.css', 'resources/js/app.jsx'],
        refresh: true,
      }),
      react(),
      // Only platform.css imports Tailwind; the landing's app.css is plain CSS and passes through untouched.
      tailwindcss(),
    ],
    resolve: {
      alias: { '@': `${import.meta.dirname}/resources/js` },
    },
    server: {
      host: '0.0.0.0',
      port,
      strictPort: true,
      hmr: { host: 'localhost', port },
      watch: { usePolling: true },
    },
  };
});
