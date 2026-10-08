/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Defaults suit running everything on your own machine; docker compose overrides them with service names.
const nodeAuthUrl = process.env.NODE_AUTH_URL ?? 'http://localhost:4000';
const apiUrl = process.env.API_URL ?? 'http://localhost:8000';

// The proxy means the browser only talks to one origin, so no CORS setup is needed in development.
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Trailing slash matters: plain '/node' would also swallow '/node_modules/...' and break the dev server.
      '/node/': { target: nodeAuthUrl, rewrite: (path) => path.replace(/^\/node/, '') },
      '/api': { target: apiUrl },
    },
  },
  test: { environment: 'node' },
});
