/**
 * c5-mocks.js – vitest setupFiles
 *
 * Definiert window.C5 mit Stub-Implementierungen aller Symbole, die
 * app.js in der Produktion bereitstellt. Muss VOR dem Import von
 * c5-asset-lookup.js geladen werden, damit das IIFE das bereits vorhandene
 * Objekt nicht überschreibt (window.C5 = window.C5 || {}).
 *
 * SYMBOLS MOCKED (aus app.js):
 *   C5.apiBase, C5.checkAuth, C5.showSpinner, C5.hideSpinner,
 *   C5.beginLoad, C5.endLoad
 */

window.C5 = {
  apiBase: '/api',

  // Passthrough – kein 401/403-Redirect im Test
  checkAuth: (res) => res,

  // Spinner-Operationen: im Test als No-ops – DOM-Mutation ist nicht relevant
  showSpinner: () => {},
  hideSpinner: () => {},

  // beginLoad/endLoad: einfaches Referenz-Zählen (wie in app.js)
  beginLoad: (form) => {
    form._pendingLoads = (form._pendingLoads || 0) + 1
  },
  endLoad: (form) => {
    form._pendingLoads = Math.max(0, (form._pendingLoads || 0) - 1)
  },
}
