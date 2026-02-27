/**
 * C5 Evidence Tool – NetBox Asset Lookup & Location/Tenant/Contact Loading
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
    asset_owner: '#asset_owner',
    service: '#service',
    criticality: '#criticality',
    admin_user: '#admin_user',
    security_owner: '#security_owner',
  };

  var _locationData = null;

  // ── Location cascading dropdowns ──

  C5.loadLocations = function (form) {
    if (!form.querySelector('#region_id')) return;

    var loadingHint = form.querySelector('#location-loading');
    var errorHint = form.querySelector('#location-error');
    var submitBtn = form.querySelector('.btn-submit');

    if (loadingHint) loadingHint.classList.remove('hidden');

    Promise.all([
      fetch(C5.apiBase + '/locations/regions').then(C5.checkAuth).then(function (r) { return r.json(); }),
      fetch(C5.apiBase + '/locations/site-groups').then(C5.checkAuth).then(function (r) { return r.json(); }),
      fetch(C5.apiBase + '/locations/sites').then(C5.checkAuth).then(function (r) { return r.json(); }),
    ])
      .then(function (results) {
        if (loadingHint) loadingHint.classList.add('hidden');

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
      })
      .catch(function () {
        if (loadingHint) loadingHint.classList.add('hidden');
        if (errorHint) errorHint.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = true;
        ['#region_id', '#site_group_id', '#site_id'].forEach(function (sel) {
          var el = form.querySelector(sel);
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
        return selectedGroupId === null || s.site_group_id === selectedGroupId || !selectedGroupId;
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
      })
      .catch(function () {
        sel.innerHTML = '<option value="">– Nicht verfügbar –</option>';
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
    var sel = form.querySelector('#asset_owner, #owner_approval, #owner');
    if (!sel) return;
    fetch(C5.apiBase + '/contacts')
      .then(C5.checkAuth)
      .then(function (r) { return r.json(); })
      .then(function (list) {
        sel.innerHTML = '<option value="" data-contact-id="">– Bitte wählen –</option>';
        list.forEach(function (c) {
          var o = document.createElement('option');
          o.value = c.name;
          o.textContent = c.name;
          o.setAttribute('data-contact-id', String(c.id));
          sel.appendChild(o);
        });
        C5.syncContactId(form);
      })
      .catch(function () {
        sel.innerHTML = '<option value="">– Nicht verfügbar –</option>';
      });
  };

  C5.syncContactId = function (form) {
    var sel = form.querySelector('#asset_owner, #owner_approval, #owner');
    var inp = form.querySelector('#contact_id');
    if (!sel || !inp) return;
    var opt = sel.options[sel.selectedIndex];
    inp.value = (opt && opt.getAttribute('data-contact-id')) || '';
  };

  // ── Asset Lookup ──

  C5.performAssetLookup = function (assetId, form) {
    if (!assetId || assetId.trim() === '') return;

    var existingBadge = form.querySelector('.netbox-badge');
    if (existingBadge) existingBadge.remove();

    fetch(C5.apiBase + '/asset-lookup?asset_id=' + encodeURIComponent(assetId))
      .then(C5.checkAuth)
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.found) return;

        Object.keys(NETBOX_FIELD_MAP).forEach(function (key) {
          var el = form.querySelector(NETBOX_FIELD_MAP[key]);
          if (el && !el.value && data[key]) {
            if (el.tagName === 'SELECT') {
              setSelectValue(el, data[key]);
            } else {
              el.value = data[key];
            }
          }
        });

        C5.syncTenantName(form);

        if (_locationData && form.querySelector('#region_id')) {
          C5.filterSiteGroups(form);
          C5.filterSites(form);
        }
        syncLocationNames(form);

        if (data.custom_fields) {
          Object.keys(NETBOX_CUSTOM_FIELD_MAP).forEach(function (key) {
            var el = form.querySelector(NETBOX_CUSTOM_FIELD_MAP[key]);
            if (el && !el.value && data.custom_fields[key]) {
              if (el.tagName === 'SELECT') {
                setSelectValue(el, data.custom_fields[key]);
              } else {
                el.value = data.custom_fields[key];
              }
            }
          });
        }

        showNetBoxBadge(form, data.status);
      })
      .catch(function () { /* silently ignore lookup errors */ });
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
