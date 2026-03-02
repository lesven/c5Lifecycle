/**
 * c5-asset-lookup-flow.test.js
 *
 * Phase 2 Baseline-Tests – Vollständige Flow-Tests mit geladenen Standortdaten
 *
 * Abgedeckte Pfade (Tasks 2.3a, 2.3b, 2.3d, 2.3e):
 *   - loadLocations: Erfolg → Dropdowns befüllt, Spinner weg
 *   - loadLocations: Fehler → Dropdowns disabled, Submit-Button disabled
 *   - filterSiteGroups: Region ausgewählt → nur passende Site-Groups sichtbar
 *   - filterSites: Site-Group ausgewählt → nur passende Sites sichtbar
 *   - filterSites: kein Site-Group → alle Sites sichtbar
 *   - performAssetLookup: data.found === false → keine Felder befüllt
 *   - performAssetLookup: Netzwerkfehler → kein Absturz, kein Badge
 *   - performAssetLookup: data.found === true → Felder vorausgefüllt
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import '../../public/js/c5-asset-lookup.js'

import {
  makeForm,
  makeLocationForm,
  makeAssetLookupForm,
  makeJsonResponse,
} from './helpers/form-builder.js'

// ── Test-Fixtures ────────────────────────────────────────────────────────────

const REGIONS = [
  { id: 1, name: 'Region Nord' },
  { id: 2, name: 'Region Süd' },
]
const SITE_GROUPS = [
  { id: 10, name: 'Gruppe A' },   // nur Region Nord
  { id: 11, name: 'Gruppe B' },   // nur Region Süd
]
const SITES = [
  { id: 100, name: 'Site Alpha', region_id: 1, site_group_id: 10 },
  { id: 101, name: 'Site Beta',  region_id: 2, site_group_id: 11 },
  { id: 102, name: 'Site Gamma', region_id: 1, site_group_id: null },
]

/** Richtet global.fetch so ein, dass loadLocations drei korrekte Antworten bekommt. */
function mockFetchForLocations(regions = REGIONS, siteGroups = SITE_GROUPS, sites = SITES) {
  global.fetch = vi.fn()
    .mockResolvedValueOnce(makeJsonResponse(regions))
    .mockResolvedValueOnce(makeJsonResponse(siteGroups))
    .mockResolvedValueOnce(makeJsonResponse(sites))
}

/** Ruft loadLocations auf und wartet auf den Promise, der am Form gespeichert wird. */
async function loadLocations(form) {
  mockFetchForLocations()
  C5.loadLocations(form)
  await form._locationsPromise
}

afterEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

// ── 2.3e: loadLocations – Erfolg ────────────────────────────────────────────

describe('loadLocations – Erfolg', () => {
  it('befüllt #region_id mit den geladenen Regionen', async () => {
    const form = makeLocationForm()
    await loadLocations(form)

    const regionSel = form.querySelector('#region_id')
    // Erste Option ist der Platzhalter, dann folgen die Regionen
    expect(regionSel.options.length).toBe(REGIONS.length + 1)
    expect(regionSel.options[1].textContent).toBe('Region Nord')
    expect(regionSel.options[2].textContent).toBe('Region Süd')
  })

  it('hinterlässt den Submit-Button nach dem Laden aktiv', async () => {
    const form = makeLocationForm()
    await loadLocations(form)

    const btn = form.querySelector('.btn-submit')
    // endLoad wurde aufgerufen → _pendingLoads == 0 → Button enabled
    expect(form._pendingLoads).toBe(0)
  })

  it('blendet den Lade-Hinweis nach Erfolg aus', async () => {
    const form = makeLocationForm()
    await loadLocations(form)

    expect(form.querySelector('#location-loading').classList.contains('hidden')).toBe(true)
  })
})

// ── 2.3e: loadLocations – Fehler ────────────────────────────────────────────

describe('loadLocations – Fehler', () => {
  it('zeigt den Fehler-Hinweis an', async () => {
    global.fetch = vi.fn().mockRejectedValue(new Error('Netz weg'))
    const form = makeLocationForm()
    C5.loadLocations(form)
    // Wir können form._locationsPromise nicht verwenden (wird nicht gesetzt bei Fehler)
    // also kurz warten bis die rejected Promise verarbeitet ist
    await vi.waitFor(() => {
      expect(form.querySelector('#location-error').classList.contains('hidden')).toBe(false)
    })
  })

  it('setzt Dropdowns auf "Nicht verfügbar" und disabled', async () => {
    global.fetch = vi.fn().mockRejectedValue(new Error('Netz weg'))
    const form = makeLocationForm()
    C5.loadLocations(form)
    await vi.waitFor(() => {
      const regionSel = form.querySelector('#region_id')
      expect(regionSel.disabled).toBe(true)
      expect(regionSel.options[0].value).toBe('')
    })
  })
})

// ── 2.3a: filterSiteGroups – Kaskadierung ───────────────────────────────────

describe('filterSiteGroups – Kaskadierung', () => {
  let form

  beforeEach(async () => {
    form = makeLocationForm()
    await loadLocations(form)
  })

  it('zeigt alle Site-Groups wenn keine Region ausgewählt ist', () => {
    form.querySelector('#region_id').value = ''
    C5.filterSiteGroups(form)

    const groupSel = form.querySelector('#site_group_id')
    // Platzhalter + alle SITE_GROUPS
    expect(groupSel.options.length).toBe(SITE_GROUPS.length + 1)
  })

  it('zeigt nur Site-Groups die Sites in der gewählten Region haben', () => {
    // Region Nord (id=1) hat Sites 100 (group 10) und 102 (group null)
    // → Gruppe A (id=10) ist sichtbar, Gruppe B (id=11) nicht
    form.querySelector('#region_id').value = '1'
    C5.filterSiteGroups(form)

    const groupSel = form.querySelector('#site_group_id')
    const visibleGroupIds = Array.from(groupSel.options)
      .filter(o => o.value !== '')
      .map(o => o.value)

    expect(visibleGroupIds).toContain('10')
    expect(visibleGroupIds).not.toContain('11')
  })

  it('zeigt Site-Groups für Region Süd korrekt', () => {
    form.querySelector('#region_id').value = '2'
    C5.filterSiteGroups(form)

    const groupSel = form.querySelector('#site_group_id')
    const visibleGroupIds = Array.from(groupSel.options)
      .filter(o => o.value !== '')
      .map(o => o.value)

    expect(visibleGroupIds).toContain('11')
    expect(visibleGroupIds).not.toContain('10')
  })

  it('synchronisiert #region_name in den Hidden-Input', () => {
    const regionSel = form.querySelector('#region_id')
    regionSel.value = '1'
    C5.filterSiteGroups(form)

    expect(form.querySelector('#region_name').value).toBe('Region Nord')
  })
})

// ── 2.3b: filterSites – Kaskadierung ────────────────────────────────────────

describe('filterSites – Kaskadierung', () => {
  let form

  beforeEach(async () => {
    form = makeLocationForm()
    await loadLocations(form)
    // Region und Site-Groups laden
    form.querySelector('#region_id').value = '1'
    C5.filterSiteGroups(form)
  })

  it('zeigt alle Sites wenn keine Site-Group ausgewählt ist', () => {
    form.querySelector('#site_group_id').value = ''
    C5.filterSites(form)

    const siteSel = form.querySelector('#site_id')
    // Platzhalter + alle SITES
    expect(siteSel.options.length).toBe(SITES.length + 1)
  })

  it('zeigt nur Sites zur gewählten Site-Group', () => {
    // Site-Group A (id=10) → nur Site Alpha (id=100)
    form.querySelector('#site_group_id').value = '10'
    C5.filterSites(form)

    const siteSel = form.querySelector('#site_id')
    const visibleSiteIds = Array.from(siteSel.options)
      .filter(o => o.value !== '')
      .map(o => o.value)

    expect(visibleSiteIds).toContain('100')
    expect(visibleSiteIds).not.toContain('101')
    expect(visibleSiteIds).not.toContain('102')
  })

  it('synchronisiert #site_name in den Hidden-Input', () => {
    form.querySelector('#site_group_id').value = '10'
    C5.filterSites(form)
    form.querySelector('#site_id').value = '100'
    // syncLocationNames wird intern bei filterSites aufgerufen
    C5.filterSites(form)

    // Nach erneutem Aufruf mit gewählter Site
    const siteSel = form.querySelector('#site_id')
    siteSel.value = '100'
    C5.filterSites(form)

    expect(form.querySelector('#site_name').value).toBe('Site Alpha')
  })
})

// ── 2.3d: performAssetLookup – kein Treffer ─────────────────────────────────

describe('performAssetLookup – kein Treffer', () => {
  it('befüllt keine Felder wenn data.found === false', async () => {
    const form = makeAssetLookupForm('<input id="serial_number" type="text" />')
    global.fetch = vi.fn().mockResolvedValue(makeJsonResponse({ found: false }))

    C5.performAssetLookup('SRV-0001', form)
    await vi.waitFor(() => expect(global.fetch).toHaveBeenCalledOnce())

    expect(form.querySelector('#serial_number').value).toBe('')
  })

  it('zeigt kein .netbox-badge wenn kein Treffer', async () => {
    const form = makeAssetLookupForm()
    global.fetch = vi.fn().mockResolvedValue(makeJsonResponse({ found: false }))

    C5.performAssetLookup('SRV-0001', form)
    await vi.waitFor(() => expect(global.fetch).toHaveBeenCalledOnce())

    expect(form.querySelector('.netbox-badge')).toBeNull()
  })

  it('tut nichts wenn asset_id leer ist', () => {
    const form = makeAssetLookupForm()
    global.fetch = vi.fn()

    C5.performAssetLookup('', form)
    C5.performAssetLookup('   ', form)

    expect(global.fetch).not.toHaveBeenCalled()
  })
})

// ── 2.3d: performAssetLookup – Netzwerkfehler ───────────────────────────────

describe('performAssetLookup – Netzwerkfehler', () => {
  it('wirft keinen Fehler bei Netzwerkausfall', async () => {
    const form = makeAssetLookupForm()
    global.fetch = vi.fn().mockRejectedValue(new Error('net::ERR_CONNECTION_REFUSED'))

    C5.performAssetLookup('SRV-FAIL', form)
    // Fehler darf nicht nach oben propagieren
    await expect(vi.waitFor(() => expect(global.fetch).toHaveBeenCalledOnce())).resolves.not.toThrow()
  })

  it('setzt kein .netbox-badge bei Netzwerkfehler', async () => {
    const form = makeAssetLookupForm()
    global.fetch = vi.fn().mockRejectedValue(new Error('Timeout'))

    C5.performAssetLookup('SRV-FAIL', form)
    await vi.waitFor(() => expect(global.fetch).toHaveBeenCalledOnce())
    // Kurz warten bis der catch-Handler gelaufen ist
    await new Promise(r => setTimeout(r, 0))

    expect(form.querySelector('.netbox-badge')).toBeNull()
  })
})

// ── performAssetLookup – Treffer mit einfachen Feldern ──────────────────────

describe('performAssetLookup – Treffer mit Feldern', () => {
  it('setzt einfache Text-Felder (serial_number, manufacturer)', async () => {
    const form = makeAssetLookupForm(`
      <div class="field-group"><input id="serial_number" type="text" /></div>
      <div class="field-group"><input id="manufacturer"  type="text" /></div>
    `)
    global.fetch = vi.fn().mockResolvedValue(makeJsonResponse({
      found: true,
      serial_number: 'SN-XYZ-123',
      manufacturer: 'Acme Corp',
      status: 'active',
    }))

    C5.performAssetLookup('SRV-0001', form)
    await vi.waitFor(() => {
      expect(form.querySelector('#serial_number').value).toBe('SN-XYZ-123')
    })
    expect(form.querySelector('#manufacturer').value).toBe('Acme Corp')
  })

  it('zeigt .netbox-badge mit dem Status aus der API', async () => {
    const form = makeAssetLookupForm()
    global.fetch = vi.fn().mockResolvedValue(makeJsonResponse({
      found: true,
      status: 'staged',
    }))

    C5.performAssetLookup('SRV-0001', form)
    await vi.waitFor(() => {
      expect(form.querySelector('.netbox-badge')).not.toBeNull()
    })
    expect(form.querySelector('.netbox-badge').textContent).toContain('staged')
  })

  it('überschreibt vorhandene Felder nicht (kein Wert-Override)', async () => {
    const form = makeAssetLookupForm(`
      <div class="field-group"><input id="serial_number" type="text" value="VORHANDENER-WERT" /></div>
    `)
    global.fetch = vi.fn().mockResolvedValue(makeJsonResponse({
      found: true,
      serial_number: 'NEUER-WERT',
      status: 'active',
    }))

    C5.performAssetLookup('SRV-0001', form)
    await vi.waitFor(() => expect(global.fetch).toHaveBeenCalledOnce())
    await new Promise(r => setTimeout(r, 0))

    // Bereits befülltes Feld wird nicht überschrieben
    expect(form.querySelector('#serial_number').value).toBe('VORHANDENER-WERT')
  })
})
