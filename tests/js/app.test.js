import { describe, it, expect, beforeEach, vi } from 'vitest'

function setupBaseC5() {
  window.C5 = {
    loadLabelsFromApi: vi.fn(),
    evaluateConditionalRequired: vi.fn(),
    loadTenants: vi.fn(),
    loadContacts: vi.fn(),
    loadLocations: vi.fn(),
    loadDeviceTypes: vi.fn(),
    filterSiteGroups: vi.fn(),
    filterSites: vi.fn(),
    syncTenantName: vi.fn(),
    syncContactId: vi.fn(),
    performAssetLookup: vi.fn(),
    validateForm: vi.fn().mockReturnValue(false),
    submitForm: vi.fn(),
  }
}

async function importApp() {
  vi.resetModules()
  await import('../../public/js/app.js')
}

function buildForm(eventType = 'rz_owner_confirm') {
  document.body.innerHTML = `
    <form id="evidence-form" data-event-type="${eventType}" data-event="${eventType}">
      <div class="field-group"><input name="asset_id" type="text"></div>
      <button class="btn-submit" type="submit">Senden</button>
    </form>
  `
  return document.getElementById('evidence-form')
}

describe('C5.getUrlParam', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    setupBaseC5()
    window.history.replaceState({}, '', '/forms/rz-owner-confirm')
  })

  it('liefert vorhandenen Parameter', async () => {
    window.history.replaceState({}, '', '/forms/rz-owner-confirm?asset_id=SRV-001')
    await importApp()

    expect(window.C5.getUrlParam('asset_id')).toBe('SRV-001')
  })

  it('normalisiert leeren Parameter auf null', async () => {
    window.history.replaceState({}, '', '/forms/rz-owner-confirm?asset_id=')
    await importApp()

    expect(window.C5.getUrlParam('asset_id')).toBeNull()
  })

  it('liefert null bei fehlendem Parameter', async () => {
    await importApp()

    expect(window.C5.getUrlParam('asset_id')).toBeNull()
  })

  it('decodiert URL-Encoding korrekt', async () => {
    window.history.replaceState({}, '', '/forms/rz-owner-confirm?asset_id=SRV%2F001%20A')
    await importApp()

    expect(window.C5.getUrlParam('asset_id')).toBe('SRV/001 A')
  })
})

describe('DOMContentLoaded prefill für rz_owner_confirm', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    setupBaseC5()
  })

  it('ruft performAssetLookup bei rz_owner_confirm + asset_id auf', async () => {
    window.history.replaceState({}, '', '/forms/rz-owner-confirm?asset_id=SRV-001')
    const form = buildForm('rz_owner_confirm')
    await importApp()

    document.dispatchEvent(new Event('DOMContentLoaded'))

    expect(form.querySelector('[name="asset_id"]').value).toBe('SRV-001')
    expect(window.C5.performAssetLookup).toHaveBeenCalledWith('SRV-001', form, { forceOverride: true })
  })

  it('ruft performAssetLookup nicht für andere event types auf', async () => {
    window.history.replaceState({}, '', '/forms/rz-provision?asset_id=SRV-001')
    buildForm('rz_provision')
    await importApp()

    document.dispatchEvent(new Event('DOMContentLoaded'))

    expect(window.C5.performAssetLookup).not.toHaveBeenCalled()
  })

  it('ruft performAssetLookup nicht bei leerem asset_id auf', async () => {
    window.history.replaceState({}, '', '/forms/rz-owner-confirm?asset_id=')
    buildForm('rz_owner_confirm')
    await importApp()

    document.dispatchEvent(new Event('DOMContentLoaded'))

    expect(window.C5.performAssetLookup).not.toHaveBeenCalled()
  })
})
