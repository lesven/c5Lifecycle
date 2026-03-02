import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    // jsdom emuliert window, document usw. – nötig für DOM-Manipulation in den Tests
    environment: 'jsdom',
    globals: true,
    // Mocks für app.js-Symbole müssen VOR dem IIFE-Import bereitstehen
    setupFiles: './tests/js/helpers/c5-mocks.js',
    coverage: {
      provider: 'v8',
      // Nur c5-asset-lookup.js tracken – das ist die zu refaktorierende Datei
      include: ['public/js/c5-asset-lookup.js'],
      thresholds: {
        branches: 85,  // Phase 4.4: von 70% auf 85% angehoben
      },
      reporter: ['text', 'html'],
      reportsDirectory: './.coverage-js',
    },
  },
})
