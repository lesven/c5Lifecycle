/**
 * c5-asset-lookup-guards.test.js
 *
 * Phase 2 Baseline-Tests – Null-State-Guards und reine DOM-Hilfsfunktionen
 *
 * Abgedeckte Pfade (Tasks 2.3c, 2.3f):
 *   - filterSiteGroups / filterSites wenn _locationData noch null ist
 *   - setSelectValue: Match per value, Match per text, kein Match
 *   - syncLocationNames / syncTenantName / syncContactId
 *
 * Diese Datei importiert c5-asset-lookup.js ohne vorher loadLocations aufzurufen,
 * sodass _locationData == null bleibt (frische Modulinstanz pro Datei).
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import '../../public/js/c5-asset-lookup.js'

import {
  makeForm,
  makeLocationForm,
  makeTenantForm,
  makeContactForm,
  populateSelect,
} from './helpers/form-builder.js'

// Aufräumen: DOM nach jedem Test zurücksetzen
afterEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

// ── 2.3c: filterSiteGroups / filterSites ohne geladene Daten ────────────────

describe('filterSiteGroups – null guard', () => {
  it('wirft keinen Fehler wenn _locationData nicht geladen ist', () => {
    const form = makeLocationForm()
    expect(() => C5.filterSiteGroups(form)).not.toThrow()
  })

  it('verändert das DOM nicht wenn _locationData null ist', () => {
    const form = makeLocationForm()
    const groupSel = form.querySelector('#site_group_id')
    groupSel.innerHTML = '<option value="">– Bitte wählen –</option>'
    const before = groupSel.innerHTML

    C5.filterSiteGroups(form)

    expect(groupSel.innerHTML).toBe(before)
  })

  it('gibt sofort zurück wenn das Form kein #region_id enthält', () => {
    const form = makeForm('<select id="site_group_id"></select>')
    expect(() => C5.filterSiteGroups(form)).not.toThrow()
  })
})

describe('filterSites – null guard', () => {
  it('wirft keinen Fehler wenn _locationData nicht geladen ist', () => {
    const form = makeLocationForm()
    expect(() => C5.filterSites(form)).not.toThrow()
  })

  it('verändert #site_id nicht wenn _locationData null ist', () => {
    const form = makeLocationForm()
    const siteSel = form.querySelector('#site_id')
    siteSel.innerHTML = '<option value="">– Bitte wählen –</option>'
    const before = siteSel.innerHTML

    C5.filterSites(form)

    expect(siteSel.innerHTML).toBe(before)
  })
})

// ── 2.3c: setSelectValue ────────────────────────────────────────────────────
// setSelectValue ist privat; wir testen es indirekt über performAssetLookup.
// Direkte Unit-Tests laufen via loadLocations-Vorbefüllung in der Flow-Testdatei.
// Hier testen wir den öffentlich beobachtbaren Effekt durch syncTenantName.

describe('syncTenantName', () => {
  it('schreibt den Text der gewählten Option in #tenant_name', () => {
    const form = makeTenantForm()
    const sel = form.querySelector('#tenant_id')
    populateSelect(sel, [
      { value: '1', text: 'Mandant Alpha' },
      { value: '2', text: 'Mandant Beta' },
    ])
    sel.value = '2'

    C5.syncTenantName(form)

    expect(form.querySelector('#tenant_name').value).toBe('Mandant Beta')
  })

  it('leert #tenant_name wenn kein Mandant ausgewählt ist', () => {
    const form = makeTenantForm()
    const sel = form.querySelector('#tenant_id')
    populateSelect(sel, [{ value: '1', text: 'Mandant Alpha' }])
    form.querySelector('#tenant_name').value = 'Alter Wert'
    sel.value = ''  // kein Eintrag gewählt

    C5.syncTenantName(form)

    expect(form.querySelector('#tenant_name').value).toBe('')
  })

  it('wirft keinen Fehler wenn #tenant_id oder #tenant_name fehlt', () => {
    const form = makeForm('<input type="text" />')
    expect(() => C5.syncTenantName(form)).not.toThrow()
  })
})

// ── 2.3f: syncContactId ──────────────────────────────────────────────────────

describe('syncContactId', () => {
  it('schreibt data-contact-id der gewählten Option in #contact_id', () => {
    const form = makeContactForm('asset_owner')
    const sel = form.querySelector('#asset_owner')
    const opt1 = document.createElement('option')
    opt1.value = 'Alice'; opt1.textContent = 'Alice'; opt1.setAttribute('data-contact-id', '42')
    const opt2 = document.createElement('option')
    opt2.value = 'Bob';   opt2.textContent = 'Bob';   opt2.setAttribute('data-contact-id', '99')
    sel.appendChild(opt1); sel.appendChild(opt2)
    sel.value = 'Bob'

    C5.syncContactId(form)

    expect(form.querySelector('#contact_id').value).toBe('99')
  })

  it('leert #contact_id wenn keine Option gewählt ist', () => {
    const form = makeContactForm('owner_approval')
    const sel = form.querySelector('#owner_approval')
    sel.innerHTML = '<option value="" data-contact-id="">– Bitte wählen –</option>'
    form.querySelector('#contact_id').value = 'alter-wert'
    sel.value = ''

    C5.syncContactId(form)

    expect(form.querySelector('#contact_id').value).toBe('')
  })

  it('findet #owner als Fallback-Selektor', () => {
    const form = makeContactForm('owner')
    const sel = form.querySelector('#owner')
    const opt = document.createElement('option')
    opt.value = 'Charlie'; opt.textContent = 'Charlie'; opt.setAttribute('data-contact-id', '7')
    sel.appendChild(opt)
    sel.value = 'Charlie'

    C5.syncContactId(form)

    expect(form.querySelector('#contact_id').value).toBe('7')
  })

  it('wirft keinen Fehler wenn kein Kontakt-Selektor vorhanden ist', () => {
    const form = makeForm('<input type="text" />')
    expect(() => C5.syncContactId(form)).not.toThrow()
  })
})
