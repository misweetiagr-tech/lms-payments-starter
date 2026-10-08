/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The proxy means the browser only talks to one origin, so no CORS setup is needed in development.
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Trailing slash matters: plain '/node' would also swallow '/node_modules/...' and break the dev server.
      '/node/': { target: 'http://localhost:4000', rewrite: (path) => path.replace(/^\/node/, '') },
      '/api': { target: 'http://localhost:8000' },
    },
  },
  test: { environment: 'node' },
});
