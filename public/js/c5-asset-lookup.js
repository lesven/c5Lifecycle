/**
 * C5 Evidence Tool – NetBox Asset Lookup & Location/Tenant/Contact Loading
 *
 * LOAD ORDER: Muss nach c5-labels.js / c5-validation.js, aber VOR app.js geladen werden.
 * Alle tatsächlichen Aufrufe erfolgen in Callbacks (nach DOMContentLoaded), daher sind
 * die app.js-Symbole zur Laufzeit immer definiert, auch wenn app.js später geladen wird.
 *
 * SYMBOLS CONSUMED FROM app.js (window.C5):
 *   C5.apiBase       – Backend-API-Basispfad
 *   C5.checkAuth     – 401/403 → Redirect nach /login
 *   C5.showSpinner   – Spinner neben einem .field-group-Element einblenden
 *   C5.hideSpinner   – Spinner wieder entfernen
 *   C5.beginLoad     – Pending-Load-Zähler erhöhen (Submit-Button disabled)
 *   C5.endLoad       – Pending-Load-Zähler senken (Submit-Button ggf. re-enabled)
 *
 * SYMBOLS EXPORTED TO window.C5 (öffentliche API, aufgerufen von app.js):
 *   C5.loadLocations      – Regionen/Site-Groups/Sites laden und Dropdowns befüllen
 *   C5.loadTenants        – Tenant-Dropdown befüllen
 *   C5.loadContacts       – Kontakt-Dropdown befüllen
 *   C5.loadDeviceTypes    – Gerätetyp-Dropdown befüllen
 *   C5.performAssetLookup – NetBox-Lookup anhand Asset-ID, Felder vorausfüllen
 *
 * SYMBOLS EXPORTED TO window.C5 (Cascade-Helfer, direkt von app.js aufgerufen):
 *   C5.filterSiteGroups – Site-Group-Dropdown auf gewählte Region filtern
 *   C5.filterSites      – Site-Dropdown auf gewählte Site-Group filtern
 *   C5.syncTenantName   – Hidden-Input #tenant_name mit Dropdown-Text synchronisieren
 *   C5.syncContactId    – Hidden-Input #contact_id mit data-contact-id synchronisieren
 *
 * REFACTORING NOTE (Phase 3-D): filterSiteGroups/filterSites/syncTenantName/syncContactId
 * sollen in Folge-Phasen durch Custom-Events intern gemacht werden.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  var NETBOX_FIELD_MAP = {
    serial_number: '#serial_number',
    manufacturer: '#manufacturer',
    model: '#model',
    device_type: '#device_type',
    site_id: '#site_id',
    site_group_id: '#site_group_id',
    region_id: '#region_id',
    tenant_id: '#tenant_id',
  };

  var NETBOX_CUSTOM_FIELD_MAP = {
    asset_owner: '#asset_owner, #owner_approval',
    service: '#service',
    criticality: '#criticality',
    admin_user: '#admin_user',
    security_owner: '#security_owner',
  };

  var _locationData = null;

  // Modul-Promise (3-C.1): wird genau einmal aufgelöst sobald loadLocations erfolgreich war.
  // performAssetLookup wartet auf diesen Promise statt auf form._locationsPromise.
  var _locationsResolve = null;
  var _locationsPromise = new Promise(function (resolve) { _locationsResolve = resolve; });

  // WeakMap (3-C.3): speichert den device-types-Promise formspezifisch ohne DOM-Mutation.
  var _deviceTypesPromises = new WeakMap();

  // Hält den AbortController des zuletzt gestarteten Asset-Lookups.
  // Bei einem neuen Aufruf wird der vorherige Request abgebrochen (3-B).
  var _pendingLookupAbort = null;

  // ── Location cascading dropdowns ──

  C5.loadLocations = function (form) {
    if (!form.querySelector('#region_id')) return;

    var loadingHint = form.querySelector('#location-loading');
    var errorHint = form.querySelector('#location-error');
    var submitBtn = form.querySelector('.btn-submit');

    if (loadingHint) loadingHint.classList.remove('hidden');

    var locationSelectors = ['#region_id', '#site_group_id', '#site_id'];
    locationSelectors.forEach(function (s) {
      var el = form.querySelector(s);
      if (el) {
        el.disabled = true;
        C5.showSpinner(el);
      }
    });
    C5.beginLoad(form);

    // Lokale Variable – kein DOM-Property mehr (3-C.4)
    Promise.all([
      fetch(C5.apiBase + '/locations/regions').then(C5.checkAuth).then(function (r) { return r.json(); }),
      fetch(C5.apiBase + '/locations/site-groups').then(C5.checkAuth).then(function (r) { return r.json(); }),
      fetch(C5.apiBase + '/locations/sites').then(C5.checkAuth).then(function (r) { return r.json(); }),
    ])
      .then(function (results) {
        if (loadingHint) loadingHint.classList.add('hidden');
        locationSelectors.forEach(function (s) {
          var el = form.querySelector(s);
          if (el) { el.disabled = false; C5.hideSpinner(el); }
        });
        C5.endLoad(form);

        if (!Array.isArray(results[0]) || !Array.isArray(results[1]) || !Array.isArray(results[2])) {
          throw new Error('Ungültige Antwort von NetBox');
        }

        _locationData = {
          regions: results[0],
          siteGroups: results[1],
          sites: results[2],
        };

        var regionSel = form.querySelector('#region_id');
        regionSel.innerHTML = '<option value="">– Bitte wählen –</option>';
        _locationData.regions.forEach(function (r) {
          var o = document.createElement('option');
          o.value = String(r.id);
          o.textContent = r.name;
          regionSel.appendChild(o);
        });

        C5.filterSiteGroups(form);
        C5.filterSites(form);

        // Modul-Promise auflösen (3-C.1) – performAssetLookup kann jetzt location-Kaskade starten
        if (_locationsResolve) {
          _locationsResolve();
          _locationsResolve = null;
        }
      })
      .catch(function () {
        if (loadingHint) loadingHint.classList.add('hidden');
        locationSelectors.forEach(function (s) {
          var el = form.querySelector(s);
          if (el) { C5.hideSpinner(el); }
        });
        C5.endLoad(form);
        if (errorHint) errorHint.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = true;
        locationSelectors.forEach(function (s) {
          var el = form.querySelector(s);
          if (el) {
            el.innerHTML = '<option value="">– Nicht verfügbar –</option>';
            el.disabled = true;
          }
        });
      });
  };

  C5.filterSiteGroups = function (form) {
    if (!_locationData) return;
    var regionSel = form.querySelector('#region_id');
    var groupSel = form.querySelector('#site_group_id');
    if (!regionSel || !groupSel) return;

    var selectedRegionId = regionSel.value ? Number(regionSel.value) : null;
    var prevGroupVal = groupSel.value;

    var visibleGroupIds = null;
    if (selectedRegionId !== null) {
      var sitesInRegion = _locationData.sites.filter(function (s) {
        return s.region_id === selectedRegionId;
      });
      visibleGroupIds = {};
      sitesInRegion.forEach(function (s) {
        if (s.site_group_id !== null && s.site_group_id !== undefined) {
          visibleGroupIds[s.site_group_id] = true;
        }
      });
    }

    groupSel.innerHTML = '<option value="">– Bitte wählen –</option>';
    _locationData.siteGroups
      .filter(function (g) {
        return visibleGroupIds === null || visibleGroupIds[g.id] === true;
      })
      .forEach(function (g) {
        var o = document.createElement('option');
        o.value = String(g.id);
        o.textContent = g.name;
        groupSel.appendChild(o);
      });

    if (prevGroupVal) groupSel.value = prevGroupVal;
    if (!groupSel.value) groupSel.selectedIndex = 0;

    C5.filterSites(form);
    syncLocationNames(form);
  };

  C5.filterSites = function (form) {
    if (!_locationData) return;
    var groupSel = form.querySelector('#site_group_id');
    var siteSel = form.querySelector('#site_id');
    if (!groupSel || !siteSel) return;

    var selectedGroupId = groupSel.value ? Number(groupSel.value) : null;
    var prevSiteVal = siteSel.value;

    siteSel.innerHTML = '<option value="">– Bitte wählen –</option>';
    _locationData.sites
      .filter(function (s) {
        // Dritte Bedingung "|| !selectedGroupId" war unerreichbar (selectedGroupId === null deckt das ab)
        return selectedGroupId === null || s.site_group_id === selectedGroupId;
      })
      .forEach(function (s) {
        var o = document.createElement('option');
        o.value = String(s.id);
        o.textContent = s.name;
        siteSel.appendChild(o);
      });

    if (prevSiteVal) siteSel.value = prevSiteVal;
    if (!siteSel.value) siteSel.selectedIndex = 0;

    syncLocationNames(form);
  };

  function syncLocationNames(form) {
    if (!_locationData) return;
    var pairs = [
      { selId: '#region_id', inputId: '#region_name', list: 'regions' },
      { selId: '#site_group_id', inputId: '#site_group_name', list: 'siteGroups' },
      { selId: '#site_id', inputId: '#site_name', list: 'sites' },
    ];
    pairs.forEach(function (p) {
      var sel = form.querySelector(p.selId);
      var inp = form.querySelector(p.inputId);
      if (!sel || !inp) return;
      var opt = sel.options[sel.selectedIndex];
      inp.value = (opt && opt.value) ? opt.text : '';
    });
  }

  // ── Tenants ──

  C5.loadTenants = function (form) {
    var sel = form.querySelector('#tenant_id');
    if (!sel) return;
    sel.disabled = true;
    C5.showSpinner(sel);
    C5.beginLoad(form);
    fetch(C5.apiBase + '/tenants')
      .then(C5.checkAuth)
      .then(function (r) { return r.json(); })
      .then(function (list) {
        sel.innerHTML = '<option value="">– Bitte wählen –</option>';
        list.forEach(function (t) {
          var o = document.createElement('option');
          o.value = String(t.id);
          o.textContent = t.name;
          sel.appendChild(o);
        });
        C5.syncTenantName(form);
        sel.disabled = false;
        C5.hideSpinner(sel);
        C5.endLoad(form);
      })
      .catch(function () {
        sel.innerHTML = '<option value="">– Nicht verfügbar –</option>';
        sel.disabled = false;
        C5.hideSpinner(sel);
        C5.endLoad(form);
      });
  };

  C5.syncTenantName = function (form) {
    var sel = form.querySelector('#tenant_id');
    var inp = form.querySelector('#tenant_name');
    if (!sel || !inp) return;
    var opt = sel.options[sel.selectedIndex];
    inp.value = (opt && opt.value) ? opt.text : '';
  };

  // ── Contacts ──

  C5.loadContacts = function (form) {
    // querySelectorAll statt querySelector: alle passenden Kontakt-Dropdowns befüllen,
    // nicht nur das erste im DOM (3-A.3)
    var sels = form.querySelectorAll('#asset_owner, #owner_approval, #owner');
    if (!sels.length) return;
    sels.forEach(function (sel) {
      sel.disabled = true;
      C5.showSpinner(sel);
    });
    C5.beginLoad(form);
    fetch(C5.apiBase + '/contacts')
      .then(C5.checkAuth)
      .then(function (r) { return r.json(); })
      .then(function (list) {
        sels.forEach(function (sel) {
          sel.innerHTML = '<option value="" data-contact-id="">– Bitte wählen –</option>';
          list.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.name;
            o.textContent = c.name;
            o.setAttribute('data-contact-id', String(c.id));
            sel.appendChild(o);
          });
          sel.disabled = false;
          C5.hideSpinner(sel);
        });
        C5.syncContactId(form);
        C5.endLoad(form);
      })
      .catch(function () {
        sels.forEach(function (sel) {
          sel.innerHTML = '<option value="">– Nicht verfügbar –</option>';
          sel.disabled = false;
          C5.hideSpinner(sel);
        });
        C5.endLoad(form);
      });
  };

  C5.syncContactId = function (form) {
    var sel = form.querySelector('#asset_owner, #owner_approval, #owner');
    var inp = form.querySelector('#contact_id');
    if (!sel || !inp) return;
    var opt = sel.options[sel.selectedIndex];
    inp.value = (opt && opt.getAttribute('data-contact-id')) || '';
  };

  // ── Device Types ──

  C5.loadDeviceTypes = function (form) {
    var sel = form.querySelector('#device_type');
    if (!sel) return;

    var eventType = form.getAttribute('data-event') || '';
    var tag = '';
    if (eventType.indexOf('rz_') === 0) {
      tag = 'rz';
    } else if (eventType.indexOf('admin_') === 0) {
      tag = 'admin';
    }

    sel.innerHTML = '<option value="">– Wird geladen … –</option>';
    sel.disabled = true;
    C5.showSpinner(sel);
    C5.beginLoad(form);

    var promise = fetch(C5.apiBase + '/device-types' + (tag ? '?tag=' + encodeURIComponent(tag) : ''))
      .then(C5.checkAuth)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) {
          throw new Error(data.error);
        }
        sel.innerHTML = '<option value="">– Bitte wählen –</option>';
        data.forEach(function (dt) {
          var o = document.createElement('option');
          o.value = dt.model;
          o.textContent = dt.model;
          o.setAttribute('data-device-type-id', String(dt.id));
          sel.appendChild(o);
        });
        sel.disabled = false;
        C5.hideSpinner(sel);
        C5.endLoad(form);
      })
      .catch(function () {
        sel.innerHTML = '<option value="">– Gerätetypen nicht verfügbar –</option>';
        sel.disabled = false;
        C5.hideSpinner(sel);
        C5.endLoad(form);
      });

    _deviceTypesPromises.set(form, promise);  // WeakMap statt form._deviceTypesPromise (3-C.3)
  };

  // ── Asset Lookup ──

  C5.performAssetLookup = function (assetId, form) {
    if (!assetId || assetId.trim() === '') return;

    // Vorherigen Request abbrechen, falls noch aktiv (3-B)
    if (_pendingLookupAbort) {
      _pendingLookupAbort.abort();
      _pendingLookupAbort = null;
    }

    var controller = new AbortController();
    _pendingLookupAbort = controller;

    var existingBadge = form.querySelector('.netbox-badge');
    if (existingBadge) existingBadge.remove();

    var assetField = form.querySelector('[name="asset_id"]');
    if (assetField) {
      assetField.readOnly = true;
      C5.showSpinner(assetField, 'Asset wird gesucht …');
    }

    fetch(C5.apiBase + '/asset-lookup?asset_id=' + encodeURIComponent(assetId), { signal: controller.signal })
      .then(C5.checkAuth)
      .then(function (res) { return res.json(); })
      .then(function (data) {
        _pendingLookupAbort = null;
        if (!data.found) return;

        // Einfache Felder direkt setzen (Standortfelder werden kaskadiert nach dem Location-Load gesetzt)
        var LOCATION_KEYS = { region_id: true, site_group_id: true, site_id: true };
        Object.keys(NETBOX_FIELD_MAP).forEach(function (key) {
          if (key === 'device_type') return; // wird nach dem Laden der Gerätetypen gesetzt
          if (LOCATION_KEYS[key]) return;    // wird kaskadiert nach _locationsPromise gesetzt
          var el = form.querySelector(NETBOX_FIELD_MAP[key]);
          if (el && !el.value && data[key]) {
            if (el.tagName === 'SELECT') {
              setSelectValue(el, data[key]);
            } else {
              el.value = data[key];
            }
          }
        });

        // Gerätetyp erst setzen, nachdem das Dropdown vollständig geladen ist
        if (data['device_type']) {
          (_deviceTypesPromises.get(form) || Promise.resolve()).then(function () {  // WeakMap statt DOM-Property (3-C.3)
            var el = form.querySelector(NETBOX_FIELD_MAP['device_type']);
            if (el && !el.value) {
              setSelectValue(el, data['device_type']);
            }
          });
        }

        C5.syncTenantName(form);

        // Region, Standortgruppe und Standort kaskadiert setzen, sobald Standortdaten geladen sind
        if ((data.region_id || data.site_group_id || data.site_id) && form.querySelector('#region_id')) {
          _locationsPromise.then(function () {  // Modul-Promise statt DOM-Property (3-C.2)
            // 1. Region setzen
            if (data.region_id) {
              var regionSel = form.querySelector('#region_id');
              if (regionSel && !regionSel.value) setSelectValue(regionSel, data.region_id);
            }
            // 2. Standortgruppe-Dropdown auf Region filtern, dann Wert setzen
            C5.filterSiteGroups(form);
            if (data.site_group_id) {
              var groupSel = form.querySelector('#site_group_id');
              if (groupSel && !groupSel.value) setSelectValue(groupSel, data.site_group_id);
            }
            // 3. Standort-Dropdown auf Gruppe filtern, dann Wert setzen
            C5.filterSites(form);
            if (data.site_id) {
              var siteSel = form.querySelector('#site_id');
              if (siteSel && !siteSel.value) setSelectValue(siteSel, data.site_id);
            }
            syncLocationNames(form);
          });
        }

        if (data.custom_fields) {
          // querySelectorAll statt querySelector: Comma-Selektoren in der Map können mehrere
          // Elemente treffen (z. B. '#asset_owner, #owner_approval') – alle befüllen (3-A.2)
          Object.keys(NETBOX_CUSTOM_FIELD_MAP).forEach(function (key) {
            form.querySelectorAll(NETBOX_CUSTOM_FIELD_MAP[key]).forEach(function (el) {
              if (!el.value && data.custom_fields[key]) {
                if (el.tagName === 'SELECT') {
                  setSelectValue(el, data.custom_fields[key]);
                } else {
                  el.value = data.custom_fields[key];
                }
              }
            });
          });
          C5.syncContactId(form);
        }

        showNetBoxBadge(form, data.status);
      })
      .catch(function (err) {
        // AbortError ist erwartet (neuer Request hat den alten abgebrochen) – kein Nutzer-Feedback (3-B)
        if (err && err.name === 'AbortError') return;
        /* andere Fehler still ignorieren */
      })
      .finally(function () {
        if (assetField) {
          assetField.readOnly = false;
          C5.hideSpinner(assetField);
        }
      });
  };

  function setSelectValue(selectEl, value) {
    var options = selectEl.options;
    for (var i = 0; i < options.length; i++) {
      if (options[i].value === value || options[i].text === value) {
        selectEl.selectedIndex = i;
        return;
      }
    }
  }

  function showNetBoxBadge(form, status) {
    var assetField = form.querySelector('[name="asset_id"]');
    if (!assetField) return;
    var group = assetField.closest('.field-group');
    if (!group) return;

    var badge = document.createElement('span');
    badge.className = 'netbox-badge';
    badge.textContent = 'NetBox: ' + (status || 'gefunden');
    group.appendChild(badge);
  }
})();
