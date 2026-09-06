import { fileURLToPath, URL } from 'node:url'

import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

/**
 * Separate from `vite.config.ts` on purpose. That one loads the Laravel plugin, which
 * refuses to start outside a development environment — correctly, since it exists to talk
 * to a dev server. Vitest needs neither the plugin nor the server, so it gets its own file
 * rather than an environment variable telling the plugin to look the other way.
 */
export default defineConfig({
  plugins: [react()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/test/setup.ts'],
    include: ['resources/js/**/*.test.{ts,tsx}'],
    css: false,
  },
})
