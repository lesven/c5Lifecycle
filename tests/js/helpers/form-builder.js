/**
 * form-builder.js – DOM-Hilfsfunktionen für Tests
 *
 * Baut minimale Form-DOM-Strukturen, die die jeweiligen C5-Funktionen benötigen.
 * Jede Helper-Funktion hängt das Form an document.body und gibt es zurück.
 * Aufräumen erfolgt im afterEach via document.body.innerHTML = ''.
 */

/**
 * Generisches Form mit beliebigem HTML-Inhalt.
 * @param {string} [html=''] - innerHTML des Form-Elements
 * @param {string} [eventType=''] - Wert des data-event-Attributs
 * @returns {HTMLFormElement}
 */
export function makeForm(html = '', eventType = '') {
  const form = document.createElement('form')
  form.id = 'evidence-form'
  if (eventType) form.setAttribute('data-event', eventType)
  form.innerHTML = html
  document.body.appendChild(form)
  return form
}

/**
 * Form mit allen drei Standort-Dropdowns (Region, Site-Group, Site)
 * sowie zugehörigen Hidden-Inputs und Lade-/Fehler-Hinweisen.
 */
export function makeLocationForm() {
  return makeForm(`
    <div class="field-group">
      <select id="region_id"><option value="">– Bitte wählen –</option></select>
    </div>
    <div class="field-group">
      <select id="site_group_id"><option value="">– Bitte wählen –</option></select>
    </div>
    <div class="field-group">
      <select id="site_id"><option value="">– Bitte wählen –</option></select>
    </div>
    <input id="region_name"     type="hidden" />
    <input id="site_group_name" type="hidden" />
    <input id="site_name"       type="hidden" />
    <button class="btn-submit">Absenden</button>
    <span id="location-loading" class="hidden"></span>
    <span id="location-error"   class="hidden"></span>
  `)
}

/** Form mit Tenant-Dropdown und Hidden-Input. */
export function makeTenantForm() {
  return makeForm(`
    <div class="field-group">
      <select id="tenant_id"><option value="">– Bitte wählen –</option></select>
    </div>
    <input id="tenant_name" type="hidden" />
  `)
}

/** Form mit Kontakt-Dropdown und Hidden-Input. */
export function makeContactForm(selectorId = 'asset_owner') {
  return makeForm(`
    <div class="field-group">
      <select id="${selectorId}"></select>
    </div>
    <input id="contact_id" type="hidden" />
  `)
}

/** Form mit asset_id-Feld (für Asset-Lookup). */
export function makeAssetLookupForm(extraHtml = '') {
  return makeForm(`
    <div class="field-group">
      <input name="asset_id" type="text" />
    </div>
    ${extraHtml}
  `)
}

/**
 * Fügt einer `<select>`-Liste Optionen hinzu.
 * @param {HTMLSelectElement} select
 * @param {Array<{value: string, text: string}>} options
 */
export function populateSelect(select, options) {
  options.forEach(({ value, text }) => {
    const o = document.createElement('option')
    o.value = value
    o.textContent = text
    select.appendChild(o)
  })
}

/** Erstellt eine minimale Fetch-Response, die JSON zurückgibt. */
export function makeJsonResponse(data) {
  return { json: () => Promise.resolve(data) }
}
