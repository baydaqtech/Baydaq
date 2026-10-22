import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
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
    port: 5173,
    hmr: { host: 'localhost' },
    watch: { usePolling: true },
  },
});
